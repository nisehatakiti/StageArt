import { useLocalSearchParams, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';
import { FormInput } from '@/components/form-input';

import { ApiError } from '@/api/errors';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import {
  useCreateNameOnlyParticipant,
  useParticipants,
  useUpdateParticipant,
} from '@/features/participant/useParticipant';
import { useParticipationRequestDecision, usePendingParticipationRequests } from '@/features/participation/useParticipation';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
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

const DELEGATE_ROLE_LABEL: Record<string, string> = {
  PARTICIPANT_MANAGER: '参加者管理',
  REHEARSAL_MANAGER: '稽古管理',
  PERFORMANCE_MANAGER: '公演スケジュール管理',
  TICKET_MANAGER: 'チケット管理',
  RESERVATION_MANAGER: '予約管理',
  CHECKIN_MANAGER: '受付・チェックイン管理',
  QUESTIONNAIRE_MANAGER: 'アンケート管理',
};
const DELEGATE_ROLES = Object.keys(DELEGATE_ROLE_LABEL);

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
  const updateParticipant = useUpdateParticipant(id);
  const production = productionQuery.data;
  const isPrimaryManager = !!production?.is_primary_manager;
  const delegatesQuery = useQuery({
    queryKey: ['production-delegates', id],
    queryFn: () => fetchProductionDelegates(apiClient, id as string),
    enabled: isPrimaryManager && !!id,
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

  const [edits, setEdits] = useState<Record<string, RowEdit>>({});
  const [memberInfoPublishedAt, setMemberInfoPublishedAt] = useState('');
  const [newName, setNewName] = useState('');
  const [newType, setNewType] = useState<string>('CAST');
  const [newRemarks, setNewRemarks] = useState('');
  const [pendingNewMembers, setPendingNewMembers] = useState<{ displayName: string; participantType: string; remarks: string }[]>([]);
  const [initialized, setInitialized] = useState(false);
  const [saving, setSaving] = useState(false);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

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
        <ScrollView horizontal showsHorizontalScrollIndicator testID="production-participants-table-scroll">
          <View style={styles.memberTable} testID="production-participants-list">
            <View style={styles.memberTableHeader}>
              <View style={styles.memberDeleteHeader} />
              <ThemedText style={styles.memberNameHeader}>名前</ThemedText>
              <ThemedText style={styles.memberRoleHeader}>役割</ThemedText>
              <ThemedText style={styles.memberRemarksHeader}>備考</ThemedText>
              {DELEGATE_ROLES.map((role) => (
                <ThemedText key={role} style={styles.permissionHeader}>
                  {DELEGATE_ROLE_LABEL[role]}
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
                canManageDelegateRoles={isPrimaryManager}
                delegateBusy={createDelegate.isPending || updateDelegate.isPending}
                onToggleDelegateRole={async (delegate, personId, role, checked) => {
                  try {
                    setErrorMessage(null);
                    if (checked) {
                      if (delegate) {
                        await updateDelegate.mutateAsync({ delegateId: delegate.id, role: delegate.role, status: 'ACTIVE' });
                      } else {
                        await createDelegate.mutateAsync({ personId, role });
                      }
                    } else if (delegate) {
                      await updateDelegate.mutateAsync({ delegateId: delegate.id, role: delegate.role, status: 'INACTIVE' });
                    }
                  } catch (error) {
                    setErrorMessage(getErrorMessage(error));
                  }
                }}
              />
            ))}
          </View>
        </ScrollView>
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
  participant, isSelf, edit, onChange, delegateRoles, canManageDelegateRoles, delegateBusy, onToggleDelegateRole,
}: {
  participant: Participant;
  isSelf: boolean;
  edit: RowEdit;
  onChange: (edit: RowEdit) => void;
  delegateRoles: ProductionDelegate[];
  canManageDelegateRoles: boolean;
  delegateBusy: boolean;
  onToggleDelegateRole: (delegate: ProductionDelegate | null, personId: string, role: string, checked: boolean) => Promise<void>;
}) {
  const displayLabel =
    participant.subject_type === 'NAME_ONLY' ? participant.display_name ?? '（氏名未設定）' :
    participant.subject_type === 'PERSON' ? (isSelf ? 'あなた' : `Person ID: ${participant.subject_id}`) :
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
      {DELEGATE_ROLES.map((role) => {
        const delegate = personDelegates.find((item) => item.role === role) ?? null;
        const checked = delegate?.status === 'ACTIVE';
        const editable = participant.subject_type === 'PERSON' && canManageDelegateRoles && !delegateBusy;
        return (
          <TouchableOpacity
            key={role}
            testID={`participant-delegate-role-${participant.id}-${role}`}
            disabled={!editable}
            onPress={() => onToggleDelegateRole(delegate, participant.subject_id, role, !checked)}
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
  error: { color: '#a6483a', marginTop: Spacing.two },
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
