import { useLocalSearchParams, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Platform, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';
import { FormInput } from '@/components/form-input';

import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useParticipants, useUpdateParticipant } from '@/features/participant/useParticipant';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import {
  useCancelParticipantInvitation,
  useCreateParticipantInvitation,
  useParticipantInvitations,
  useResendParticipantInvitation,
} from '@/features/participantInvitation/useParticipantInvitation';
import { updateProduction } from '@/features/production/api';
import { useProduction } from '@/features/production/useProductions';
import { useProductionOrganization } from '@/features/production/useProductionOrganization';
import { useAuth } from '@/auth/AuthContext';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import type { Participant } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';
import {
  createProductionDelegate,
  fetchProductionDelegates,
  updateProductionDelegate,
} from '@/features/productionDelegate/api';
import type { ProductionDelegate } from '@/types/api';

const PARTICIPANT_TYPE_LABEL: Record<string, string> = { CAST: '出演者', STAFF: 'スタッフ' };
const PARTICIPANT_TYPES = ['CAST', 'STAFF'] as const;

/**
 * StageArt 担当者権限をメンバー管理へ統合・整理 instruction §1/§2/§3: exactly
 * these 3 checkboxes are offered to general members, in this exact order.
 * 代理人 deliberately bundles two existing, independent RoleKeys
 * (PARTICIPANT_MANAGER + REHEARSAL_MANAGER) behind one checkbox per the
 * instruction's explicit confirmation - it does not collapse them into a
 * single new RoleKey, so toggling always resolves both underlying
 * ProductionDelegate rows individually. TICKET_MANAGER/PERFORMANCE_MANAGER/
 * QUESTIONNAIRE_MANAGER/RESERVATION_MANAGER are deliberately absent here
 * (§3's "だけ" - PrimaryManager-side concern, still reachable via the
 * existing 担当者 screen for now, per §5's "いきなり削除しない").
 */
const DELEGATE_CHECKBOXES = [
  { key: 'proxy', label: '代理人', roles: ['PARTICIPANT_MANAGER', 'REHEARSAL_MANAGER'] },
  { key: 'accounting', label: '会計担当', roles: ['ACCOUNTING_MANAGER'] },
  { key: 'checkin', label: '受付担当', roles: ['CHECKIN_MANAGER'] },
] as const;

type RowEdit = { participantType: string; remarks: string; delete: boolean };

function localDatePart(value: string): string {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value.slice(0, 10) : date.toLocaleDateString('sv-SE');
}

function localTimePart(value: string): string {
  if (!value) return '';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value.slice(11, 16) : date.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit', hour12: false });
}

function composePublishedAt(datePart: string, timePart: string): string {
  return new Date(datePart + 'T' + (timePart || '00:00') + ':00').toISOString();
}

/**
 * StageArt Phase 1 (docs/21-MemberManagementScreen.md): rebuilt around
 * the Blueprint's single-list, single-[更新]-button model. Role/remarks
 * edits and new-member additions/deletions are staged in local state and
 * only sent to the server when [更新] is pressed - each existing
 * granular REST endpoint (PUT /participants/{id}, POST .../participants,
 * DELETE /participants/{id}) is still used underneath, called together
 * from this one Action rather than immediately per-interaction as the
 * previous screen did. メンバー情報公開日時 is one Production-wide field
 * (§21.7), saved via the same PUT /productions/{id} the Production
 * Information screen already uses.
 *
 * 参加申請 (usePendingParticipationRequests) remains its own, separately-
 * backed section - approving/rejecting a request is a distinct business
 * action from editing the roster's role/remarks, and Participant.md's
 * PENDING status already gives it its own real Domain lifecycle
 * (unlike role/remarks, which have no "draft" concept server-side).
 */
export default function ProductionParticipantsScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();
  const productionQuery = useProduction(id);
  const currentPersonQuery = useCurrentPerson();
  const participantsQuery = useParticipants(id);
  const updateParticipant = useUpdateParticipant(id);
  const production = productionQuery.data;
  const isPrimaryManager = !!production?.is_primary_manager;
  /**
   * 代理人 (PROXY manager): a member holding BOTH PARTICIPANT_MANAGER and
   * REHEARSAL_MANAGER simultaneously - mirrors
   * ProductionAuthorizationService::isProxyManager() Backend-side. This is
   * deliberately a DIFFERENT (broader) check than `canManage` below: opening
   * this screen only requires PARTICIPANT_MANAGER alone, while setting
   * delegate roles (canManageDelegateRoles) requires the full 代理人 bundle -
   * per the instruction's explicit "別のAuthorization" framing.
   */
  const isProxyManager =
    !!production?.delegate_roles?.includes('PARTICIPANT_MANAGER') &&
    !!production?.delegate_roles?.includes('REHEARSAL_MANAGER');
  const canManageDelegateRoles = isPrimaryManager || isProxyManager;
  const delegatesQuery = useQuery({
    queryKey: ['production-delegates', id],
    queryFn: () => fetchProductionDelegates(apiClient, id as string),
    enabled: canManageDelegateRoles && !!id,
  });
  const createDelegate = useMutation({
    mutationFn: (fields: { personId: string; role: string }) => createProductionDelegate(apiClient, id as string, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['production-delegates', id] }),
  });
  const updateDelegate = useMutation({
    mutationFn: ({ delegateId, role, status }: { delegateId: string; role: string; status: string }) =>
      updateProductionDelegate(apiClient, delegateId, { role, status }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['production-delegates', id] }),
  });

  const { organization } = useProductionOrganization(production);
  const canManage = !!production?.is_primary_manager || !!production?.delegate_roles?.includes('PARTICIPANT_MANAGER');
  const activeParticipants = (participantsQuery.data ?? []).filter((participant) => participant.status === 'ACTIVE');

  const invitationsQuery = useParticipantInvitations(id, canManage);
  const pendingInvitations = (invitationsQuery.data ?? []).filter((invitation) => invitation.status === 'PENDING');
  const createInvitation = useCreateParticipantInvitation(id);
  const resendInvitation = useResendParticipantInvitation(id);
  const cancelInvitation = useCancelParticipantInvitation(id);

  const [edits, setEdits] = useState<Record<string, RowEdit>>({});
  const [memberInfoPublishedAt, setMemberInfoPublishedAt] = useState('');
  const [initialized, setInitialized] = useState(false);
  const [saving, setSaving] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  /**
   * StageArt Productionメンバー追加(氏名＋メールアドレス統一)ラウンド §1/§2:
   * the one, unified "メンバーを追加" form - 氏名＋メールアドレス＋役割＋備考
   * submitted together via POST /productions/{id}/participant-invitations,
   * which itself decides (by email) whether this becomes an existing-
   * Person Participant, a new invitation, or a resend - see
   * CreateParticipantInvitationUseCase's own docblock. No Person ID
   * input, no standalone "search by email" step, and no NAME_ONLY-only
   * add path remain in this UI (§0/§1/§11/§12).
   */
  const [newMemberName, setNewMemberName] = useState('');
  const [newMemberEmail, setNewMemberEmail] = useState('');
  const [newMemberType, setNewMemberType] = useState<string>('CAST');
  const [newMemberRemarks, setNewMemberRemarks] = useState('');
  const [addMemberMessage, setAddMemberMessage] = useState<string | null>(null);

  useEffect(() => {
    if (activeParticipants.length > 0) {
      setEdits((current) => {
        const next = { ...current };
        for (const participant of activeParticipants) {
          if (!next[participant.id]) {
            next[participant.id] = {
              participantType: participant.participant_type,
              remarks: participant.remarks ?? '',
              delete: false,
            };
          }
        }
        return next;
      });
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [participantsQuery.data]);

  useEffect(() => {
    if (production && !initialized) {
      setMemberInfoPublishedAt(production.member_info_published_at ?? '');
      setInitialized(true);
    }
  }, [production, initialized]);

  /**
   * §2: one action, 氏名＋メールアドレス＋役割＋備考 submitted together.
   * The Backend decides (by email) which of the three outcomes applies -
   * this handler only needs to surface which one happened.
   */
  async function handleAddMember() {
    const name = newMemberName.trim();
    const email = newMemberEmail.trim();
    if (!name || !email) {
      return;
    }
    setErrorMessage(null);
    setAddMemberMessage(null);
    try {
      const result = await createInvitation.mutateAsync({
        name,
        email,
        participantType: newMemberType,
        remarks: newMemberRemarks.trim() || null,
      });
      setAddMemberMessage(
        result.outcome === 'PARTICIPANT_ADDED'
          ? 'メンバーを追加しました。'
          : result.outcome === 'INVITATION_RESENT'
            ? '既に招待済みのため、登録案内メールを再送しました。'
            : '登録案内メールを送信しました。本人の登録が完了すると自動的にメンバーへ追加されます。'
      );
      setNewMemberName('');
      setNewMemberEmail('');
      setNewMemberRemarks('');
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handleSave() {
    if (!production) {
      return;
    }

    setSaving(true);
    setErrorMessage(null);

    try {
      const now = new Date().toISOString();
      const hasAnyMember = activeParticipants.some((p) => !edits[p.id]?.delete);

      // §21.9 Update Behavior: existing-member deletions, role/remarks
      // changes, and the publication date/time are applied together
      // here. New-member addition is its own immediate action
      // (handleAddMember, via POST .../participant-invitations) - not
      // part of this batch (§2's "実行したら...処理する").
      for (const participant of activeParticipants) {
        const edit = edits[participant.id];
        if (!edit) continue;

        if (edit.delete) {
          await useCancelParticipantDirect(apiClient, participant.id);
          continue;
        }

        if (edit.participantType !== participant.participant_type || edit.remarks !== (participant.remarks ?? '')) {
          await updateParticipant.mutateAsync({ id: participant.id, participantType: edit.participantType, status: 'ACTIVE', remarks: edit.remarks || null });
        }
      }

      await updateProduction(apiClient, production.id, {
        name: production.name,
        titleHeading: production.title_heading,
        memberInfoPublishedAt: hasAnyMember ? memberInfoPublishedAt.trim() || production.member_info_published_at || now : null,
      });

      await queryClient.invalidateQueries({ queryKey: ['participants', id] });
      await queryClient.invalidateQueries({ queryKey: ['production', id] });
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  const breadcrumbs = [
    { label: 'StageArt', href: '/dashboard' as Href },
    { label: '団体', href: '/organizations' as Href },
    ...(organization ? [{ label: organization.name, href: `/organizations/${organization.id}` as Href }] : []),
    ...(organization ? [{ label: '公演', href: `/organizations/${organization.id}/productions` as Href }] : []),
    { label: production?.name ?? '...', href: `/productions/${id}` as Href },
    { label: 'メンバー管理' },
  ];

  if (productionQuery.isLoading) {
    return (
      <>
        <ActivityIndicator testID="production-participants-loading" />
      </>
    );
  }

  if (!production) {
    return (
      <>
        <ThemedText testID="production-participants-not-found">この公演が見つかりません。</ThemedText>
      </>
    );
  }

  if (!canManage) {
    return (
      <>
        <ThemedText testID="production-participants-forbidden">
          メンバー管理はPrimaryManagerまたは参加者管理の権限を持つ担当者のみ利用できます。
        </ThemedText>
      </>
    );
  }

  const memberTable = (
    <View style={styles.memberTable} testID="production-participants-list">
      <View style={styles.memberTableHeader}>
        <View style={styles.memberDeleteHeader} />
        <ThemedText style={styles.memberNameHeader}>名前</ThemedText>
        <ThemedText style={styles.memberRoleHeader}>役割</ThemedText>
        <ThemedText style={styles.memberRemarksHeader}>備考</ThemedText>
        <ThemedText style={styles.memberEmailHeader}>メールアドレス</ThemedText>
        <ThemedText style={styles.memberAddActionHeader}>操作</ThemedText>
        {canManageDelegateRoles &&
          DELEGATE_CHECKBOXES.map((checkbox) => (
            <ThemedText key={checkbox.key} style={styles.permissionHeader}>
              {checkbox.label}
            </ThemedText>
          ))}
      </View>
      {activeParticipants.map((participant) => (
        <ParticipantEditRow
          key={participant.id}
          participant={participant}
          isSelf={participant.subject_type === 'PERSON' && participant.subject_id === currentPersonQuery.data?.id}
          edit={edits[participant.id] ?? { participantType: participant.participant_type, remarks: participant.remarks ?? '', delete: false }}
          onChange={(edit) => setEdits((current) => ({ ...current, [participant.id]: edit }))}
          delegateRoles={delegatesQuery.data ?? []}
          canManageDelegateRoles={canManageDelegateRoles}
          delegateBusy={createDelegate.isPending || updateDelegate.isPending}
          onToggleDelegateCheckbox={async (personId, roles, checked) => {
            const delegates = delegatesQuery.data ?? [];
            setErrorMessage(null);
            try {
              for (const role of roles) {
                const existing = delegates.find((delegate) => delegate.person_id === personId && delegate.role === role);
                if (checked) {
                  if (!existing) {
                    await createDelegate.mutateAsync({ personId, role });
                  } else if (existing.status !== 'ACTIVE') {
                    await updateDelegate.mutateAsync({ delegateId: existing.id, role, status: 'ACTIVE' });
                  }
                } else if (existing && existing.status === 'ACTIVE') {
                  await updateDelegate.mutateAsync({ delegateId: existing.id, role, status: 'INACTIVE' });
                }
              }
            } catch (error) {
              setErrorMessage(getErrorMessage(error));
            }
          }}
        />
      ))}

      <View style={styles.addMemberTableRow} testID="production-participants-add-member-form">
        <View style={styles.memberDeleteHeader} />
        <ThemedTextInput
          testID="production-participants-new-member-name"
          value={newMemberName}
          onChangeText={setNewMemberName}
          placeholder="氏名"
          style={styles.addMemberNameInput}
        />
        <View style={styles.addMemberRoleCell}>
          {PARTICIPANT_TYPES.map((type) => (
            <TouchableOpacity
              key={type}
              testID={`production-participants-new-member-type-${type}`}
              onPress={() => setNewMemberType(type)}
              style={[styles.roleButton, newMemberType === type && styles.roleButtonActive]}
            >
              <ThemedText type="small" style={newMemberType === type ? styles.typeButtonTextActive : undefined}>
                {PARTICIPANT_TYPE_LABEL[type]}
              </ThemedText>
            </TouchableOpacity>
          ))}
        </View>
        <ThemedTextInput
          testID="production-participants-new-member-remarks"
          value={newMemberRemarks}
          onChangeText={setNewMemberRemarks}
          placeholder="備考"
          style={styles.addMemberRemarksInput}
        />
        <ThemedTextInput
          testID="production-participants-new-member-email"
          value={newMemberEmail}
          onChangeText={setNewMemberEmail}
          placeholder="メールアドレス"
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="email-address"
          style={styles.addMemberEmailInput}
        />
        <TouchableOpacity
          testID="production-participants-add-member"
          onPress={handleAddMember}
          disabled={!newMemberName.trim() || !newMemberEmail.trim() || createInvitation.isPending}
          style={styles.addMemberAction}
        >
          {createInvitation.isPending ? (
            <ActivityIndicator color={BrandColors.warmAmber} />
          ) : (
            <ThemedText style={styles.addButtonText}>＋ メンバーを追加</ThemedText>
          )}
        </TouchableOpacity>
        {canManageDelegateRoles &&
          DELEGATE_CHECKBOXES.map((checkbox) => (
            <View key={checkbox.key} style={styles.permissionCell} />
          ))}
      </View>
    </View>
  );

  const invitationTable = (
    <View style={styles.invitationTable} testID="production-participant-invitations-list">
      <View style={styles.memberTableHeader}>
        <ThemedText style={styles.invitationNameHeader}>氏名</ThemedText>
        <ThemedText style={styles.invitationEmailHeader}>メールアドレス</ThemedText>
        <ThemedText style={styles.invitationTypeHeader}>役割</ThemedText>
        <ThemedText style={styles.invitationStatusHeader}>状態</ThemedText>
        <ThemedText style={styles.invitationExpiresHeader}>有効期限</ThemedText>
        <View style={styles.invitationActionsHeader} />
      </View>
      {pendingInvitations.map((invitation) => (
        <View key={invitation.id} style={styles.memberTableRow} testID={`participant-invitation-row-${invitation.id}`}>
          <ThemedText style={styles.invitationNameCell}>{invitation.name}</ThemedText>
          <ThemedText style={styles.invitationEmailCell}>{invitation.email}</ThemedText>
          <ThemedText style={styles.invitationTypeCell}>{PARTICIPANT_TYPE_LABEL[invitation.participant_type] ?? invitation.participant_type}</ThemedText>
          <ThemedText style={styles.invitationStatusCell}>{invitation.is_expired ? '期限切れ' : '招待中'}</ThemedText>
          <ThemedText type="small" themeColor="textSecondary" style={styles.invitationExpiresCell}>
            {new Date(invitation.expires_at).toLocaleString('ja-JP')}
          </ThemedText>
          <View style={styles.invitationActionsCell}>
            <TouchableOpacity
              testID={`participant-invitation-resend-${invitation.id}`}
              onPress={() => resendInvitation.mutate(invitation.id)}
              disabled={resendInvitation.isPending || cancelInvitation.isPending}
              style={styles.invitationActionButton}
            >
              <ThemedText type="small">再送</ThemedText>
            </TouchableOpacity>
            <TouchableOpacity
              testID={`participant-invitation-cancel-${invitation.id}`}
              onPress={() => cancelInvitation.mutate(invitation.id)}
              disabled={resendInvitation.isPending || cancelInvitation.isPending}
              style={styles.invitationActionButton}
            >
              <ThemedText type="small">取消</ThemedText>
            </TouchableOpacity>
          </View>
        </View>
      ))}
    </View>
  );

  return (
    <>
      {/*
       * StageArt Productionメンバー追加(氏名＋メールアドレス統一)ラウンド
       * §13: the whole screen's content lives inside ONE vertical
       * ScrollView (the same `<ScrollView contentContainerStyle={...}>`
       * pattern production/[id]/edit.tsx already uses) - AppChrome's own
       * web shell (WebSidebarNav) bounds `{children}` to the viewport
       * height via its own flex:1 chain with no scroll affordance of its
       * own, so a screen that returns a bare Fragment (as this one did
       * before) has its overflow silently clipped on web instead of
       * scrolling - this is what made the 更新 button unreachable.
       * Nesting the horizontal member/invitation tables' own
       * Platform.OS==='web' View (overflow:'scroll', see previous
       * round) INSIDE this outer vertical ScrollView is a standard
       * nested-scroll layout and does not reintroduce that bug - the
       * horizontal View still only scrolls its own axis.
       */}
      <ScrollView contentContainerStyle={styles.pageContainer}>
      <ThemedText type="title" style={styles.pageTitle}>
        メンバー管理
      </ThemedText>

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        登録済みメンバー
      </ThemedText>

      {participantsQuery.isLoading && <ActivityIndicator testID="production-participants-list-loading" />}
      {participantsQuery.isError && (
        <ThemedText testID="production-participants-list-error">{getErrorMessage(participantsQuery.error)}</ThemedText>
      )}
      {!participantsQuery.isLoading && !participantsQuery.isError && activeParticipants.length === 0 && (
        <ThemedText testID="production-participants-empty" themeColor="textSecondary">
          まだ参加者がいません。
        </ThemedText>
      )}
      {Platform.OS === 'web' ? (
        <View style={styles.webTableScroll} testID="production-participants-table-scroll">
          {memberTable}
        </View>
      ) : (
        <ScrollView horizontal showsHorizontalScrollIndicator testID="production-participants-table-scroll">
          {memberTable}
        </ScrollView>
      )}

      {pendingInvitations.length > 0 && (
        <>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            招待中のメンバー
          </ThemedText>
          {Platform.OS === 'web' ? (
            <View style={styles.webTableScroll} testID="production-participant-invitations-table-scroll">
              {invitationTable}
            </View>
          ) : (
            <ScrollView horizontal showsHorizontalScrollIndicator testID="production-participant-invitations-table-scroll">
              {invitationTable}
            </ScrollView>
          )}
        </>
      )}

      <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
        メンバー情報公開日時（任意）
      </ThemedText>
      <View style={styles.datetimeRow}>
        <View style={styles.datetimeField}>
          <ThemedText type="small" themeColor="textSecondary">公開日</ThemedText>
          <FormInput
            testID="production-participants-published-date"
            kind="date"
            value={localDatePart(memberInfoPublishedAt)}
            onChangeText={(date) => {
              if (!date) {
                setMemberInfoPublishedAt('');
                return;
              }
              const time = localTimePart(memberInfoPublishedAt) || '00:00';
              setMemberInfoPublishedAt(composePublishedAt(date, time));
            }}
            style={styles.datetimeInput}
          />
        </View>
        <View style={styles.datetimeField}>
          <ThemedText type="small" themeColor="textSecondary">公開時刻</ThemedText>
          <FormInput
            testID="production-participants-published-time"
            kind="time"
            value={localTimePart(memberInfoPublishedAt)}
            onChangeText={(time) => {
              if (!time) {
                setMemberInfoPublishedAt('');
                return;
              }
              const date = localDatePart(memberInfoPublishedAt) || new Date().toLocaleDateString('sv-SE');
              setMemberInfoPublishedAt(composePublishedAt(date, time));
            }}
            style={styles.datetimeInput}
          />
        </View>
        <TouchableOpacity
          testID="production-participants-published-clear"
          onPress={() => setMemberInfoPublishedAt('')}
          style={styles.clearDateButton}
        >
          <ThemedText type="small">クリア</ThemedText>
        </TouchableOpacity>
      </View>
      <ThemedText type="small" themeColor="textSecondary" style={styles.hint}>
        ※設定日時になるまで、Production公開ページのメンバー情報は表示しません。
      </ThemedText>

      <TouchableOpacity
        testID="production-participants-save"
        onPress={handleSave}
        disabled={saving}
        style={[styles.button, saving && styles.buttonDisabled]}
      >
        {saving ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>更新</ThemedText>}
      </TouchableOpacity>
      </ScrollView>
    </>
  );
}

function ParticipantEditRow({
  participant, isSelf, edit, onChange, delegateRoles, canManageDelegateRoles, delegateBusy, onToggleDelegateCheckbox,
}: {
  participant: Participant;
  isSelf: boolean;
  edit: RowEdit;
  onChange: (edit: RowEdit) => void;
  delegateRoles: ProductionDelegate[];
  canManageDelegateRoles: boolean;
  delegateBusy: boolean;
  onToggleDelegateCheckbox: (personId: string, roles: string[], checked: boolean) => Promise<void>;
}) {
  /**
   * StageArt Production側氏名の権威付けラウンド §0/§11: `display_name` is
   * this Production's own record of the member's name (entered on the
   * member-add form), independent of the linked Person's own
   * familyName/givenName - it takes priority here. `personFullName` is
   * only a fallback for PERSON rows that predate this round or were
   * created through a path with no name entered, never a value this
   * round overwrites `display_name` with.
   */
  const personFullName = [participant.person_family_name, participant.person_given_name].filter(Boolean).join(' ');
  const displayLabel =
    participant.subject_type === 'NAME_ONLY' ? participant.display_name ?? '（氏名未設定）' :
    participant.subject_type === 'PERSON' ? (isSelf ? 'あなた' : participant.display_name || personFullName || '（氏名未設定）') :
    `Organization ID: ${participant.subject_id}`;
  const personDelegates = participant.subject_type === 'PERSON'
    ? delegateRoles.filter((delegate) => delegate.person_id === participant.subject_id)
    : [];

  return (
    <View style={[styles.memberTableRow, edit.delete && styles.memberTableRowDeleted]} testID={`participant-row-${participant.id}`}>
      <View style={styles.memberDeleteCell}>
        <TouchableOpacity
          testID={`participant-delete-checkbox-${participant.id}`}
          onPress={() => onChange({ ...edit, delete: !edit.delete })}
          accessibilityRole="checkbox"
          accessibilityState={{ checked: edit.delete }}
        >
          <ThemedText style={styles.tableCheckbox}>{edit.delete ? '☑' : '□'}</ThemedText>
        </TouchableOpacity>
      </View>
      <ThemedText style={[styles.memberNameCell, edit.delete && styles.strikethrough]}>{displayLabel}</ThemedText>
      <View style={styles.memberRoleCell}>
        {PARTICIPANT_TYPES.map((type) => (
          <TouchableOpacity
            key={type}
            testID={`participant-type-${participant.id}-${type}`}
            onPress={() => onChange({ ...edit, participantType: type })}
            accessibilityRole="radio"
            accessibilityState={{ selected: edit.participantType === type }}
            style={[styles.roleButton, edit.participantType === type && styles.roleButtonActive]}
          >
            <ThemedText type="small" style={edit.participantType === type ? styles.roleButtonTextActive : undefined}>
              {PARTICIPANT_TYPE_LABEL[type]}
            </ThemedText>
          </TouchableOpacity>
        ))}
      </View>
      <ThemedTextInput
        testID={`participant-remarks-${participant.id}`}
        value={edit.remarks}
        onChangeText={(remarks) => onChange({ ...edit, remarks })}
        placeholder="備考"
        style={styles.memberRemarksCell}
      />
      {canManageDelegateRoles &&
        DELEGATE_CHECKBOXES.map((checkbox) => {
          const checked = checkbox.roles.every((role) =>
            personDelegates.some((delegate) => delegate.role === role && delegate.status === 'ACTIVE')
          );
          const editable = participant.subject_type === 'PERSON' && !delegateBusy;
          return (
            <TouchableOpacity
              key={checkbox.key}
              testID={`participant-delegate-${checkbox.key}-${participant.id}`}
              disabled={!editable}
              onPress={() => onToggleDelegateCheckbox(participant.subject_id, [...checkbox.roles], !checked)}
              accessibilityRole="checkbox"
              accessibilityState={{ checked, disabled: !editable }}
              style={[styles.permissionCell, !editable && styles.permissionCellDisabled]}
            >
              <ThemedText style={styles.tableCheckbox}>{checked ? '☑' : '□'}</ThemedText>
            </TouchableOpacity>
          );
        })}
    </View>
  );
}

/** Cancel is applied inline within the batched save loop, matching the
 * same ApiClient the other mutations here use (no separate hook needed
 * for a call this narrow). */
async function useCancelParticipantDirect(apiClient: ReturnType<typeof useAuth>['apiClient'], participantId: string): Promise<void> {
  await apiClient.delete<void>(`/participants/${participantId}`);
}

const styles = StyleSheet.create({
  /** §13: contentContainerStyle for the whole-screen vertical
   * ScrollView - same shape as production/[id]/edit.tsx's own
   * pageContainer (padding only, no flex tricks needed for a plain
   * vertical ScrollView). */
  pageContainer: { padding: Spacing.five, paddingBottom: Spacing.six },
  pageTitle: { marginBottom: Spacing.two },
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.one },
  fieldLabel: { marginTop: Spacing.one },
  hint: { marginBottom: Spacing.one },
  list: { gap: Spacing.one },
  memberEmailHeader: { width: 260, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  memberEmailCell: { width: 260, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  memberAddActionHeader: { width: 150, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600', textAlign: 'center' },
  addMemberTableRow: {
    flexDirection: 'row',
    alignItems: 'stretch',
    minHeight: 58,
    borderTopWidth: 1,
    borderTopColor: '#ddd',
  },
  addMemberNameInput: {
    width: 180,
    margin: Spacing.one,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 6,
    paddingHorizontal: Spacing.one,
    paddingVertical: 6,
    fontSize: 16,
  },
  addMemberRemarksInput: {
    width: 300,
    margin: Spacing.one,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 6,
    paddingHorizontal: Spacing.one,
    paddingVertical: 6,
    fontSize: 16,
  },
  addMemberEmailInput: {
    width: 260,
    margin: Spacing.one,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 6,
    paddingHorizontal: Spacing.one,
    paddingVertical: 6,
    fontSize: 16,
  },
  addMemberAction: {
    width: 150,
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    margin: Spacing.one,
    paddingVertical: Spacing.one,
  },
  error: { color: '#a6483a', marginTop: Spacing.two },
  invitationMessage: { color: BrandColors.warmAmber, marginTop: Spacing.one, marginBottom: Spacing.one },
  invitationTable: {
    minWidth: 1060,
    borderWidth: 1,
    borderColor: '#ddd',
    backgroundColor: '#fff',
  },
  invitationNameHeader: { width: 160, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  invitationNameCell: { width: 160, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  invitationEmailHeader: { width: 260, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  invitationEmailCell: { width: 260, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  invitationTypeHeader: { width: 100, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  invitationTypeCell: { width: 100, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  invitationStatusHeader: { width: 100, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  invitationStatusCell: { width: 100, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  invitationExpiresHeader: { width: 200, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  invitationExpiresCell: { width: 200, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, textAlignVertical: 'center' },
  invitationActionsHeader: { width: 140 },
  invitationActionsCell: { width: 140, flexDirection: 'row', gap: Spacing.two, alignItems: 'center', paddingHorizontal: Spacing.two },
  invitationActionButton: { borderWidth: 1, borderColor: '#ccc', borderRadius: Radius.medium, paddingVertical: 4, paddingHorizontal: 8 },
  button: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
    marginTop: Spacing.three,
    alignSelf: 'flex-start',
  },
  buttonDisabled: { opacity: 0.6 },
  buttonText: { color: '#fff', fontWeight: '600' },
  approveButton: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.one,
    paddingHorizontal: Spacing.two,
  },
  approveButtonText: { color: '#fff', fontWeight: '600' },
  rejectButton: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.one,
    paddingHorizontal: Spacing.two,
  },
  rejectButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
});      <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
        メンバー情報公開日時（任意）
      </ThemedText>
      <View style={styles.datetimeRow}>
        <View style={styles.datetimeField}>
          <ThemedText type="small" themeColor="textSecondary">公開日</ThemedText>
          <FormInput
            testID="production-participants-published-date"
            kind="date"
            value={localDatePart(memberInfoPublishedAt)}
            onChangeText={(date) => {
              if (!date) {
                setMemberInfoPublishedAt('');
                return;
              }
              const time = localTimePart(memberInfoPublishedAt) || '00:00';
              setMemberInfoPublishedAt(composePublishedAt(date, time));
            }}
            style={styles.datetimeInput}
          />
        </View>
        <View style={styles.datetimeField}>
          <ThemedText type="small" themeColor="textSecondary">公開時刻</ThemedText>
          <FormInput
            testID="production-participants-published-time"
            kind="time"
            value={localTimePart(memberInfoPublishedAt)}
            onChangeText={(time) => {
              if (!time) {
                setMemberInfoPublishedAt('');
                return;
              }
              const date = localDatePart(memberInfoPublishedAt) || new Date().toLocaleDateString('sv-SE');
              setMemberInfoPublishedAt(composePublishedAt(date, time));
            }}
            style={styles.datetimeInput}
          />
        </View>
        <TouchableOpacity
          testID="production-participants-published-clear"
          onPress={() => setMemberInfoPublishedAt('')}
          style={styles.clearDateButton}
        >
          <ThemedText type="small">クリア</ThemedText>
        </TouchableOpacity>
      </View>
      <ThemedText type="small" themeColor="textSecondary" style={styles.hint}>
        ※設定日時になるまで、Production公開ページのメンバー情報は表示しません。
      </ThemedText>

      {errorMessage && (
        <ThemedText testID="production-participants-error" style={styles.error}>
          {errorMessage}
        </ThemedText>
      )}


