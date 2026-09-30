import { useLocalSearchParams, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Platform, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';
import { FormInput } from '@/components/form-input';

import { ApiError } from '@/api/errors';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import {
  useCreateNameOnlyParticipant,
  useCreatePersonParticipant,
  useParticipants,
  useUpdateParticipant,
} from '@/features/participant/useParticipant';
import { useParticipationRequestDecision, usePendingParticipationRequests } from '@/features/participation/useParticipation';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import { fetchPersonById, searchPersonByEmail } from '@/features/person/api';
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
import type { Participant, PersonSummary } from '@/types/api';
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
  const pendingQuery = usePendingParticipationRequests(id);
  const { approve, reject } = useParticipationRequestDecision(id);
  const participantsQuery = useParticipants(id);
  const createNameOnly = useCreateNameOnlyParticipant(id);
  const createPersonParticipant = useCreatePersonParticipant(id);
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
  const [newName, setNewName] = useState('');
  const [newType, setNewType] = useState<string>('CAST');
  const [newRemarks, setNewRemarks] = useState('');
  const [pendingNewMembers, setPendingNewMembers] = useState<{ displayName: string; participantType: string; remarks: string }[]>([]);
  const [initialized, setInitialized] = useState(false);
  const [saving, setSaving] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  // メンバー追加§2-A: Person ID検索 → プレビュー → 既存Personとして追加。
  const [personIdInput, setPersonIdInput] = useState('');
  const [personSearchResult, setPersonSearchResult] = useState<PersonSummary | null>(null);
  const [personSearchError, setPersonSearchError] = useState<string | null>(null);
  const [personSearching, setPersonSearching] = useState(false);
  const [personAddType, setPersonAddType] = useState<string>('CAST');
  const [personAddRemarks, setPersonAddRemarks] = useState('');

  // メール招待によるProductionParticipant追加機能 §22: emailによる検索 →
  // 既存Personが見つかれば追加、見つからなければ招待メール送信。
  const [emailInput, setEmailInput] = useState('');
  const [emailSearchResult, setEmailSearchResult] = useState<PersonSummary | null>(null);
  const [emailSearchNotFound, setEmailSearchNotFound] = useState(false);
  const [emailSearchError, setEmailSearchError] = useState<string | null>(null);
  const [emailSearching, setEmailSearching] = useState(false);
  const [emailAddType, setEmailAddType] = useState<string>('CAST');
  const [emailAddRemarks, setEmailAddRemarks] = useState('');
  const [invitationMessage, setInvitationMessage] = useState<string | null>(null);

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

  function addPendingMember() {
    if (!newName.trim()) {
      return;
    }
    setPendingNewMembers((current) => [...current, { displayName: newName.trim(), participantType: newType, remarks: newRemarks.trim() }]);
    setNewName('');
    setNewRemarks('');
  }

  function removePendingMember(index: number) {
    setPendingNewMembers((current) => current.filter((_, i) => i !== index));
  }

  async function handleSearchPerson() {
    const personId = personIdInput.trim();
    if (!personId) {
      return;
    }
    setPersonSearching(true);
    setPersonSearchError(null);
    setPersonSearchResult(null);
    try {
      const person = await fetchPersonById(apiClient, personId);
      setPersonSearchResult(person);
    } catch (error) {
      setPersonSearchError(
        error instanceof ApiError && error.statusCode === 404
          ? 'このPerson IDのメンバーが見つかりません。IDをご確認ください。'
          : getErrorMessage(error)
      );
    } finally {
      setPersonSearching(false);
    }
  }

  async function handleAddFoundPerson() {
    if (!personSearchResult) {
      return;
    }
    setErrorMessage(null);
    try {
      await createPersonParticipant.mutateAsync({
        personId: personSearchResult.id,
        participantType: personAddType,
        remarks: personAddRemarks.trim() || null,
      });
      setPersonIdInput('');
      setPersonSearchResult(null);
      setPersonAddRemarks('');
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handleSearchEmail() {
    const email = emailInput.trim();
    if (!email) {
      return;
    }
    setEmailSearching(true);
    setEmailSearchError(null);
    setEmailSearchResult(null);
    setEmailSearchNotFound(false);
    try {
      const person = await searchPersonByEmail(apiClient, email, id as string);
      setEmailSearchResult(person);
    } catch (error) {
      if (error instanceof ApiError && error.statusCode === 404) {
        setEmailSearchNotFound(true);
      } else {
        setEmailSearchError(getErrorMessage(error));
      }
    } finally {
      setEmailSearching(false);
    }
  }

  async function handleAddFoundPersonByEmail() {
    if (!emailSearchResult) {
      return;
    }
    setErrorMessage(null);
    setInvitationMessage(null);
    try {
      await createPersonParticipant.mutateAsync({
        personId: emailSearchResult.id,
        participantType: emailAddType,
        remarks: emailAddRemarks.trim() || null,
      });
      setEmailInput('');
      setEmailSearchResult(null);
      setEmailAddRemarks('');
    } catch (error) {
      setErrorMessage(getErrorMessage(error));
    }
  }

  async function handleSendInvitation() {
    const email = emailInput.trim();
    if (!email) {
      return;
    }
    setErrorMessage(null);
    setInvitationMessage(null);
    try {
      const result = await createInvitation.mutateAsync({
        email,
        participantType: emailAddType,
        remarks: emailAddRemarks.trim() || null,
      });
      setInvitationMessage(
        result.outcome === 'INVITATION_RESENT'
          ? '既に招待済みのため、招待メールを再送しました。'
          : '招待メールを送信しました。本人の登録が完了すると自動的にメンバーへ追加されます。'
      );
      setEmailInput('');
      setEmailSearchNotFound(false);
      setEmailAddRemarks('');
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
      const hasAnyMember = activeParticipants.some((p) => !edits[p.id]?.delete) || pendingNewMembers.length > 0;

      // §21.9 Update Behavior: additions, deletions, role/remarks changes,
      // and the publication date/time are all applied together here.
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

      for (const pending of pendingNewMembers) {
        await createNameOnly.mutateAsync({ displayName: pending.displayName, participantType: pending.participantType, remarks: pending.remarks || null });
      }

      await updateProduction(apiClient, production.id, {
        name: production.name,
        titleHeading: production.title_heading,
        memberInfoPublishedAt: hasAnyMember ? memberInfoPublishedAt.trim() || production.member_info_published_at || now : null,
      });

      await queryClient.invalidateQueries({ queryKey: ['participants', id] });
      await queryClient.invalidateQueries({ queryKey: ['production', id] });
      setPendingNewMembers([]);
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
    </View>
  );

  const invitationTable = (
    <View style={styles.invitationTable} testID="production-participant-invitations-list">
      <View style={styles.memberTableHeader}>
        <ThemedText style={styles.invitationEmailHeader}>メールアドレス</ThemedText>
        <ThemedText style={styles.invitationTypeHeader}>役割</ThemedText>
        <ThemedText style={styles.invitationStatusHeader}>状態</ThemedText>
        <ThemedText style={styles.invitationExpiresHeader}>有効期限</ThemedText>
        <View style={styles.invitationActionsHeader} />
      </View>
      {pendingInvitations.map((invitation) => (
        <View key={invitation.id} style={styles.memberTableRow} testID={`participant-invitation-row-${invitation.id}`}>
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
      <ThemedText type="title" style={styles.pageTitle}>
        メンバー管理
      </ThemedText>

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        参加申請
      </ThemedText>

      {pendingQuery.isLoading && <ActivityIndicator testID="production-participation-requests-loading" />}
      {pendingQuery.isError && (
        <ThemedText testID="production-participation-requests-error">
          {pendingQuery.error instanceof ApiError && pendingQuery.error.statusCode === 403
            ? '参加申請の管理はPrimaryManagerまたは参加者管理の権限を持つ担当者のみ利用できます。'
            : getErrorMessage(pendingQuery.error)}
        </ThemedText>
      )}
      {!pendingQuery.isLoading && !pendingQuery.isError && (pendingQuery.data?.length ?? 0) === 0 && (
        <ThemedText testID="production-participation-requests-empty" themeColor="textSecondary">
          現在、参加申請はありません。
        </ThemedText>
      )}
      {(pendingQuery.data?.length ?? 0) > 0 && (
        <View style={styles.list} testID="production-participation-requests-list">
          {pendingQuery.data?.map((request) => (
            <View key={request.id} style={styles.row} testID={`participation-request-row-${request.id}`}>
              <ThemedText style={styles.requestName}>{[request.person_family_name, request.person_given_name].filter(Boolean).join(' ') || '（氏名未設定）'}</ThemedText>
              <ThemedText type="small" themeColor="textSecondary" style={styles.requestType}>
                {PARTICIPANT_TYPE_LABEL[request.participant_type] ?? request.participant_type}
              </ThemedText>
              <View style={[styles.colAction, styles.actionButtons]}>
                <TouchableOpacity
                  testID={`participation-request-approve-${request.id}`}
                  onPress={() => approve.mutate(request.id)}
                  disabled={approve.isPending || reject.isPending}
                  style={styles.approveButton}
                >
                  <ThemedText style={styles.approveButtonText}>承認</ThemedText>
                </TouchableOpacity>
                <TouchableOpacity
                  testID={`participation-request-reject-${request.id}`}
                  onPress={() => reject.mutate(request.id)}
                  disabled={approve.isPending || reject.isPending}
                  style={styles.rejectButton}
                >
                  <ThemedText style={styles.rejectButtonText}>却下</ThemedText>
                </TouchableOpacity>
              </View>
            </View>
          ))}
        </View>
      )}

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
      {activeParticipants.length > 0 && (
        Platform.OS === 'web' ? (
          <View style={styles.webTableScroll} testID="production-participants-table-scroll">
            {memberTable}
          </View>
        ) : (
          <ScrollView horizontal showsHorizontalScrollIndicator testID="production-participants-table-scroll">
            {memberTable}
          </ScrollView>
        )
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

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        メンバーを追加
      </ThemedText>

      <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
        既存メンバーをPerson IDで検索して追加
      </ThemedText>
      <View style={styles.personSearchRow}>
        <ThemedTextInput
          testID="production-participants-person-id-input"
          value={personIdInput}
          onChangeText={(value) => {
            setPersonIdInput(value);
            setPersonSearchResult(null);
            setPersonSearchError(null);
          }}
          placeholder="Person ID"
          style={styles.personSearchInput}
        />
        <TouchableOpacity
          testID="production-participants-person-search"
          onPress={handleSearchPerson}
          disabled={!personIdInput.trim() || personSearching}
          style={[styles.addButton, styles.personSearchButton]}
        >
          {personSearching ? <ActivityIndicator color={BrandColors.warmAmber} /> : <ThemedText style={styles.addButtonText}>検索</ThemedText>}
        </TouchableOpacity>
      </View>
      {personSearchError && (
        <ThemedText testID="production-participants-person-search-error" style={styles.error}>
          {personSearchError}
        </ThemedText>
      )}
      {personSearchResult && (
        <View style={styles.personPreview} testID="production-participants-person-preview">
          <ThemedText style={styles.pendingMemberName}>
            {[personSearchResult.family_name, personSearchResult.given_name].filter(Boolean).join(' ') || '（氏名未設定）'}
          </ThemedText>
          <View style={styles.typeToggle}>
            {PARTICIPANT_TYPES.map((type) => (
              <TouchableOpacity
                key={type}
                testID={`production-participants-person-add-type-${type}`}
                onPress={() => setPersonAddType(type)}
                style={[styles.typeButton, personAddType === type && styles.typeButtonActive]}
              >
                <ThemedText style={personAddType === type ? styles.typeButtonTextActive : undefined}>{PARTICIPANT_TYPE_LABEL[type]}</ThemedText>
              </TouchableOpacity>
            ))}
          </View>
          <ThemedTextInput
            testID="production-participants-person-add-remarks"
            value={personAddRemarks}
            onChangeText={setPersonAddRemarks}
            placeholder="備考"
            style={styles.input}
          />
          <TouchableOpacity
            testID="production-participants-person-add-confirm"
            onPress={handleAddFoundPerson}
            disabled={createPersonParticipant.isPending}
            style={styles.addButton}
          >
            {createPersonParticipant.isPending ? (
              <ActivityIndicator color={BrandColors.warmAmber} />
            ) : (
              <ThemedText style={styles.addButtonText}>このメンバーを追加</ThemedText>
            )}
          </TouchableOpacity>
        </View>
      )}

      <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
        メールアドレスで検索・招待
      </ThemedText>
      <View style={styles.personSearchRow}>
        <ThemedTextInput
          testID="production-participants-email-input"
          value={emailInput}
          onChangeText={(value) => {
            setEmailInput(value);
            setEmailSearchResult(null);
            setEmailSearchNotFound(false);
            setEmailSearchError(null);
            setInvitationMessage(null);
          }}
          placeholder="メールアドレス"
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="email-address"
          style={styles.personSearchInput}
        />
        <TouchableOpacity
          testID="production-participants-email-search"
          onPress={handleSearchEmail}
          disabled={!emailInput.trim() || emailSearching}
          style={[styles.addButton, styles.personSearchButton]}
        >
          {emailSearching ? <ActivityIndicator color={BrandColors.warmAmber} /> : <ThemedText style={styles.addButtonText}>検索</ThemedText>}
        </TouchableOpacity>
      </View>
      {emailSearchError && (
        <ThemedText testID="production-participants-email-search-error" style={styles.error}>
          {emailSearchError}
        </ThemedText>
      )}
      {invitationMessage && (
        <ThemedText testID="production-participants-invitation-message" style={styles.invitationMessage}>
          {invitationMessage}
        </ThemedText>
      )}
      {(emailSearchResult || emailSearchNotFound) && (
        <View style={styles.personPreview} testID="production-participants-email-preview">
          {emailSearchResult ? (
            <ThemedText style={styles.pendingMemberName}>
              {[emailSearchResult.family_name, emailSearchResult.given_name].filter(Boolean).join(' ') || '（氏名未設定）'}
            </ThemedText>
          ) : (
            <ThemedText testID="production-participants-email-not-found" type="small" themeColor="textSecondary">
              このメールアドレスのStageArtメンバーは見つかりませんでした。招待メールを送信できます。
            </ThemedText>
          )}
          <View style={styles.typeToggle}>
            {PARTICIPANT_TYPES.map((type) => (
              <TouchableOpacity
                key={type}
                testID={`production-participants-email-add-type-${type}`}
                onPress={() => setEmailAddType(type)}
                style={[styles.typeButton, emailAddType === type && styles.typeButtonActive]}
              >
                <ThemedText style={emailAddType === type ? styles.typeButtonTextActive : undefined}>{PARTICIPANT_TYPE_LABEL[type]}</ThemedText>
              </TouchableOpacity>
            ))}
          </View>
          <ThemedTextInput
            testID="production-participants-email-add-remarks"
            value={emailAddRemarks}
            onChangeText={setEmailAddRemarks}
            placeholder="備考"
            style={styles.input}
          />
          {emailSearchResult ? (
            <TouchableOpacity
              testID="production-participants-email-add-confirm"
              onPress={handleAddFoundPersonByEmail}
              disabled={createPersonParticipant.isPending}
              style={styles.addButton}
            >
              {createPersonParticipant.isPending ? (
                <ActivityIndicator color={BrandColors.warmAmber} />
              ) : (
                <ThemedText style={styles.addButtonText}>このメンバーを追加</ThemedText>
              )}
            </TouchableOpacity>
          ) : (
            <TouchableOpacity
              testID="production-participants-email-invite-confirm"
              onPress={handleSendInvitation}
              disabled={createInvitation.isPending}
              style={styles.addButton}
            >
              {createInvitation.isPending ? (
                <ActivityIndicator color={BrandColors.warmAmber} />
              ) : (
                <ThemedText style={styles.addButtonText}>招待メールを送信</ThemedText>
              )}
            </TouchableOpacity>
          )}
        </View>
      )}

      <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
        Person IDが分からない場合は、氏名のみで登録
      </ThemedText>
      <ThemedText type="small" themeColor="textSecondary">
        氏名
      </ThemedText>
      <ThemedTextInput testID="production-participants-new-name" value={newName} onChangeText={setNewName} style={styles.input} />

      <ThemedText type="small" themeColor="textSecondary">
        役割
      </ThemedText>
      <View style={styles.typeToggle}>
        {PARTICIPANT_TYPES.map((type) => (
          <TouchableOpacity
            key={type}
            testID={`production-participants-new-type-${type}`}
            onPress={() => setNewType(type)}
            style={[styles.typeButton, newType === type && styles.typeButtonActive]}
          >
            <ThemedText style={newType === type ? styles.typeButtonTextActive : undefined}>{PARTICIPANT_TYPE_LABEL[type]}</ThemedText>
          </TouchableOpacity>
        ))}
      </View>

      <ThemedText type="small" themeColor="textSecondary">
        備考
      </ThemedText>
      <ThemedTextInput testID="production-participants-new-remarks" value={newRemarks} onChangeText={setNewRemarks} style={styles.input} />

      <TouchableOpacity testID="production-participants-add" onPress={addPendingMember} disabled={!newName.trim()} style={styles.addButton}>
        <ThemedText style={styles.addButtonText}>＋ 追加</ThemedText>
      </TouchableOpacity>

      {pendingNewMembers.length > 0 && (
        <View style={styles.list} testID="production-participants-pending-list">
          {pendingNewMembers.map((pending, index) => (
            <View key={`${pending.displayName}-${index}`} style={styles.pendingMemberRow} testID={`production-participants-pending-${index}`}>
              <ThemedText style={styles.pendingMemberName}>{pending.displayName}</ThemedText>
              <ThemedText type="small" themeColor="textSecondary">
                {PARTICIPANT_TYPE_LABEL[pending.participantType]}
              </ThemedText>
              <TouchableOpacity testID={`production-participants-pending-remove-${index}`} onPress={() => removePendingMember(index)}>
                <ThemedText type="link">取り消し</ThemedText>
              </TouchableOpacity>
            </View>
          ))}
        </View>
      )}

      {errorMessage && (
        <ThemedText testID="production-participants-error" style={styles.error}>
          {errorMessage}
        </ThemedText>
      )}

      <TouchableOpacity
        testID="production-participants-save"
        onPress={handleSave}
        disabled={saving}
        style={[styles.button, saving && styles.buttonDisabled]}
      >
        {saving ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>更新</ThemedText>}
      </TouchableOpacity>
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
  const personFullName = [participant.person_family_name, participant.person_given_name].filter(Boolean).join(' ');
  const displayLabel =
    participant.subject_type === 'NAME_ONLY' ? participant.display_name ?? '（氏名未設定）' :
    participant.subject_type === 'PERSON' ? (isSelf ? 'あなた' : personFullName || '（氏名未設定）') :
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
  pageTitle: { marginBottom: Spacing.two },
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.one },
  fieldLabel: { marginTop: Spacing.one },
  hint: { marginBottom: Spacing.one },
  list: { gap: Spacing.one },
  row: {
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
  },
  webTableScroll: {
    width: '100%',
    overflow: 'auto',
    maxWidth: '100%',
    marginBottom: Spacing.two,
  },
  memberTable: {
    minWidth: 1500,
    borderWidth: 1,
    borderColor: '#ddd',
    backgroundColor: '#fff',
  },
  memberTableHeader: {
    flexDirection: 'row',
    alignItems: 'stretch',
    backgroundColor: '#f5f3ef',
    borderBottomWidth: 1,
    borderBottomColor: '#ddd',
  },
  memberTableRow: {
    flexDirection: 'row',
    alignItems: 'stretch',
    minHeight: 58,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#e5e5e5',
  },
  memberTableRowDeleted: { opacity: 0.5 },
  memberDeleteHeader: { width: 42 },
  memberDeleteCell: { width: 42, justifyContent: 'center', alignItems: 'center' },
  memberNameHeader: { width: 180, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  memberNameCell: { width: 180, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600', textAlignVertical: 'center' },
  memberRoleHeader: { width: 150, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  memberRoleCell: { width: 150, flexDirection: 'row', alignItems: 'center', gap: 4, paddingHorizontal: Spacing.one },
  roleButton: { borderWidth: 1, borderColor: '#ccc', borderRadius: Radius.medium, paddingVertical: 4, paddingHorizontal: 8 },
  roleButtonActive: { backgroundColor: BrandColors.warmAmber, borderColor: BrandColors.warmAmber },
  roleButtonTextActive: { color: '#fff' },
  memberRemarksHeader: { width: 300, paddingHorizontal: Spacing.two, paddingVertical: Spacing.two, fontWeight: '600' },
  memberRemarksCell: { width: 300, margin: Spacing.one, borderWidth: 1, borderColor: '#ccc', borderRadius: 6, paddingHorizontal: Spacing.one, paddingVertical: 6 },
  permissionHeader: { width: 150, paddingHorizontal: Spacing.one, paddingVertical: Spacing.two, fontWeight: '600', textAlign: 'center' },
  permissionCell: { width: 150, justifyContent: 'center', alignItems: 'center', borderLeftWidth: StyleSheet.hairlineWidth, borderLeftColor: '#e5e5e5' },
  permissionCellDisabled: { opacity: 0.55 },
  tableCheckbox: { fontSize: 20 },
  strikethrough: { textDecorationLine: 'line-through', opacity: 0.5 },
  actionButtons: { flexDirection: 'row', gap: Spacing.two },
  colAction: { marginLeft: 'auto' },
  typeToggle: { flexDirection: 'row', gap: Spacing.one, marginBottom: Spacing.one },
  typeButton: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.one,
    paddingHorizontal: Spacing.three,
  },
  typeButtonSmall: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
    paddingVertical: 4,
    paddingHorizontal: Spacing.two,
  },
  typeButtonActive: { backgroundColor: BrandColors.warmAmber, borderColor: BrandColors.warmAmber },
  typeButtonTextActive: { color: '#fff' },
  datetimeRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'flex-end', gap: Spacing.two, marginBottom: Spacing.one },
  datetimeField: { width: 220, minWidth: 180 },
  datetimeInput: { marginTop: 4 },
  clearDateButton: { borderWidth: 1, borderColor: '#ccc', borderRadius: Radius.medium, paddingVertical: Spacing.one, paddingHorizontal: Spacing.two },
  remarksInput: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.one,
  },
  requestRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two, paddingVertical: Spacing.two, borderBottomWidth: StyleSheet.hairlineWidth, borderBottomColor: '#eee' },
  requestName: { width: 220, fontWeight: '600' },
  requestType: { minWidth: 100 },
  pendingMemberRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two, paddingVertical: Spacing.one },
  pendingMemberName: { fontWeight: '600' },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
    marginBottom: Spacing.two,
  },
  addButton: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    alignItems: 'center',
    marginTop: Spacing.one,
  },
  addButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  personSearchRow: { flexDirection: 'row', gap: Spacing.two, alignItems: 'center', marginBottom: Spacing.two },
  personSearchInput: {
    flex: 1,
    minWidth: 160,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
  },
  personSearchButton: { marginTop: 0, paddingHorizontal: Spacing.three },
  personPreview: {
    borderWidth: 1,
    borderColor: '#ddd',
    borderRadius: Radius.medium,
    padding: Spacing.two,
    marginBottom: Spacing.two,
    gap: Spacing.one,
  },
  error: { color: '#a6483a', marginTop: Spacing.two },
  invitationMessage: { color: BrandColors.warmAmber, marginTop: Spacing.one, marginBottom: Spacing.one },
  invitationTable: {
    minWidth: 900,
    borderWidth: 1,
    borderColor: '#ddd',
    backgroundColor: '#fff',
  },
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
});
