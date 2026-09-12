import { useLocalSearchParams, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

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
import { useQueryClient } from '@tanstack/react-query';
import type { Participant } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

const PARTICIPANT_TYPE_LABEL: Record<string, string> = { CAST: '出演者', STAFF: 'スタッフ' };
const PARTICIPANT_TYPES = ['CAST', 'STAFF'] as const;

type RowEdit = { participantType: string; remarks: string; delete: boolean };

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
  const { organization } = useProductionOrganization(production);
  const canManage = !!production?.is_primary_manager || production?.delegate_role === 'PARTICIPANT_MANAGER';
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
              <ThemedText style={styles.colName}>{[request.person_family_name, request.person_given_name].filter(Boolean).join(' ') || '（氏名未設定）'}</ThemedText>
              <ThemedText type="small" themeColor="textSecondary" style={styles.colType}>
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
        <View style={styles.list} testID="production-participants-list">
          {activeParticipants.map((participant) => (
            <ParticipantEditRow
              key={participant.id}
              participant={participant}
              isSelf={participant.subject_type === 'PERSON' && participant.subject_id === currentPersonQuery.data?.id}
              edit={edits[participant.id] ?? { participantType: participant.participant_type, remarks: participant.remarks ?? '', delete: false }}
              onChange={(edit) => setEdits((current) => ({ ...current, [participant.id]: edit }))}
            />
          ))}
        </View>
      )}

      <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
        メンバー情報公開日時（任意・ISO 8601）
      </ThemedText>
      <ThemedTextInput
        testID="production-participants-published-at"
        value={memberInfoPublishedAt}
        onChangeText={setMemberInfoPublishedAt}
        placeholder="未設定の場合、保存時に自動で公開されます"
        style={styles.input}
      />
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
            <View key={`${pending.displayName}-${index}`} style={styles.row} testID={`production-participants-pending-${index}`}>
              <ThemedText style={styles.colName}>{pending.displayName}</ThemedText>
              <ThemedText type="small" themeColor="textSecondary" style={styles.colType}>
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
  participant,
  isSelf,
  edit,
  onChange,
}: {
  participant: Participant;
  isSelf: boolean;
  edit: RowEdit;
  onChange: (edit: RowEdit) => void;
}) {
  const displayLabel =
    participant.subject_type === 'NAME_ONLY'
      ? participant.display_name ?? '（氏名未設定）'
      : participant.subject_type === 'PERSON'
        ? isSelf
          ? 'あなた'
          : `Person ID: ${participant.subject_id}`
        : `Organization ID: ${participant.subject_id}`;

  return (
    <View style={styles.row} testID={`participant-row-${participant.id}`}>
      <TouchableOpacity
        testID={`participant-delete-checkbox-${participant.id}`}
        onPress={() => onChange({ ...edit, delete: !edit.delete })}
        style={styles.checkbox}
      >
        <ThemedText>{edit.delete ? '☑' : '☐'}</ThemedText>
      </TouchableOpacity>
      <ThemedText style={[styles.colName, edit.delete && styles.strikethrough]}>{displayLabel}</ThemedText>
      <View style={styles.typeToggle}>
        {PARTICIPANT_TYPES.map((type) => (
          <TouchableOpacity
            key={type}
            testID={`participant-type-${participant.id}-${type}`}
            onPress={() => onChange({ ...edit, participantType: type })}
            style={[styles.typeButtonSmall, edit.participantType === type && styles.typeButtonActive]}
          >
            <ThemedText type="small" style={edit.participantType === type ? styles.typeButtonTextActive : undefined}>
              {PARTICIPANT_TYPE_LABEL[type]}
            </ThemedText>
          </TouchableOpacity>
        ))}
      </View>
      <ThemedTextInput
        testID={`participant-remarks-${participant.id}`}
        value={edit.remarks}
        onChangeText={(remarks) => onChange({ ...edit, remarks })}
        style={styles.remarksInput}
      />
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
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
    gap: Spacing.two,
  },
  checkbox: { width: 24 },
  colName: { width: 200 },
  colType: { width: 100 },
  colAction: { width: 160 },
  strikethrough: { textDecorationLine: 'line-through', opacity: 0.5 },
  actionButtons: { flexDirection: 'row', gap: Spacing.two },
  typeToggle: { flexDirection: 'row', gap: Spacing.one, marginBottom: Spacing.two },
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
  remarksInput: {
    flex: 1,
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.one,
  },
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
