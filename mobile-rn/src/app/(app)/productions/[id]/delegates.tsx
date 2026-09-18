import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ApiError } from '@/api/errors';
import { ThemedText } from '@/components/themed-text';
import { ThemedTextInput } from '@/components/themed-text-input';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useParticipants } from '@/features/participant/useParticipant';
import { useProduction } from '@/features/production/useProductions';
import {
  useCreateProductionDelegate,
  useDeleteProductionDelegate,
  useProductionDelegates,
  useUpdateProductionDelegate,
} from '@/features/productionDelegate/useProductionDelegate';
import type { Participant, ProductionDelegate } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

const ROLE_LABEL: Record<string, string> = {
  PARTICIPANT_MANAGER: '参加者管理',
  REHEARSAL_MANAGER: '稽古管理',
  PERFORMANCE_MANAGER: '公演回管理',
  TICKET_MANAGER: 'チケット管理',
  RESERVATION_MANAGER: '予約管理',
  CHECKIN_MANAGER: '受付・チェックイン管理',
  QUESTIONNAIRE_MANAGER: 'アンケート管理',
};

/**
 * §6: exactly these 7 Production-scope Roles - the Backend's RoleKey
 * enum also technically accepts OWNER/MEMBER (Organization-scope values
 * that leaked into the same validation), but those are never offered
 * here.
 */
const ROLES = [
  'PARTICIPANT_MANAGER',
  'REHEARSAL_MANAGER',
  'PERFORMANCE_MANAGER',
  'TICKET_MANAGER',
  'RESERVATION_MANAGER',
  'CHECKIN_MANAGER',
  'QUESTIONNAIRE_MANAGER',
] as const;

const PARTICIPANT_TYPE_LABEL: Record<string, string> = { CAST: '出演者', STAFF: 'スタッフ' };

function personLabel(delegate: ProductionDelegate): string {
  const name = [delegate.person_family_name, delegate.person_given_name].filter(Boolean).join(' ');
  return name || '名前未設定';
}

/**
 * ProductionDelegate実用化 instruction: makes the already-complete
 * ProductionDelegate Backend (list/create/update/delete, all
 * PrimaryManager-only per canManageProductionDelegates()) actually
 * usable from the app. User-facing label is "担当者" throughout - the
 * Domain name "ProductionDelegate"/"Delegate" never appears in the UI.
 *
 * Candidate discovery for "add a delegate" deliberately does not use a
 * Person search API, because none exists anywhere in StageArt today
 * (confirmed by reading MembershipRestController/OrganizationRestController/
 * ParticipantResult before writing this screen - organizations/[id]/
 * members.tsx already documents the identical gap for a different
 * feature). Candidates are sourced from this Production's own existing
 * Participant list (an already-implemented, already-fetched "Production
 * Member API"), filtered to real StageArt Persons (subject_type ===
 * 'PERSON' - a NAME_ONLY Participant has no PersonId and the Backend
 * would reject it). Participant's own API does not resolve a name for a
 * PERSON-type subject either (a separate, pre-existing gap, deliberately
 * not fixed here - out of this feature's scope) - candidate cards are
 * therefore honestly labelled without a name, never a fabricated one. A
 * manual Person ID field is offered alongside, for a target who exists
 * as a Person but is not yet a Participant (the Backend allows this -
 * CreateProductionDelegateUseCase only requires the Person to exist).
 */
export default function ProductionDelegatesScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const isPrimaryManager = !!production?.is_primary_manager;

  const delegatesQuery = useProductionDelegates(id);
  const participantsQuery = useParticipants(id);
  const createDelegate = useCreateProductionDelegate(id);
  const updateDelegate = useUpdateProductionDelegate(id);
  const deleteDelegate = useDeleteProductionDelegate(id);

  const [targetPersonId, setTargetPersonId] = useState('');
  const [newRole, setNewRole] = useState<string>(ROLES[0]);
  const [addErrorMessage, setAddErrorMessage] = useState<string | null>(null);
  const [rowErrorMessage, setRowErrorMessage] = useState<Record<string, string>>({});
  const [confirmingDeleteId, setConfirmingDeleteId] = useState<string | null>(null);

  const personCandidates: Participant[] = (participantsQuery.data ?? []).filter(
    (participant) => participant.subject_type === 'PERSON' && participant.status === 'ACTIVE'
  );

  async function handleAdd() {
    setAddErrorMessage(null);

    if (!targetPersonId.trim()) {
      return;
    }

    try {
      await createDelegate.mutateAsync({ personId: targetPersonId.trim(), role: newRole });
      setTargetPersonId('');
    } catch (error) {
      if (error instanceof ApiError && error.code === 'stageart_production_delegate_already_exists') {
        setAddErrorMessage('この方は、選択したRoleで既に担当者として登録されています。');
      } else if (error instanceof ApiError && error.code === 'stageart_production_delegate_target_not_eligible') {
        setAddErrorMessage('指定されたPersonが見つかりません。Person IDをご確認ください。');
      } else if (error instanceof ApiError && error.statusCode === 403) {
        setAddErrorMessage('担当者の設定はPrimaryManagerのみ行えます。');
      } else {
        setAddErrorMessage(getErrorMessage(error));
      }
    }
  }

  async function handleChangeRole(delegate: ProductionDelegate, role: string) {
    setRowErrorMessage((current) => ({ ...current, [delegate.id]: '' }));
    try {
      await updateDelegate.mutateAsync({ id: delegate.id, role, status: delegate.status });
    } catch (error) {
      setRowErrorMessage((current) => ({ ...current, [delegate.id]: getErrorMessage(error) }));
    }
  }

  async function handleToggleStatus(delegate: ProductionDelegate) {
    setRowErrorMessage((current) => ({ ...current, [delegate.id]: '' }));
    const nextStatus = delegate.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
    try {
      await updateDelegate.mutateAsync({ id: delegate.id, role: delegate.role, status: nextStatus });
    } catch (error) {
      setRowErrorMessage((current) => ({ ...current, [delegate.id]: getErrorMessage(error) }));
    }
  }

  async function handleRemove(delegate: ProductionDelegate) {
    setRowErrorMessage((current) => ({ ...current, [delegate.id]: '' }));
    try {
      await deleteDelegate.mutateAsync(delegate.id);
      setConfirmingDeleteId(null);
    } catch (error) {
      setRowErrorMessage((current) => ({ ...current, [delegate.id]: getErrorMessage(error) }));
    }
  }

  if (productionQuery.isLoading) {
    return (
      <>
        <ActivityIndicator testID="production-delegates-loading" />
      </>
    );
  }

  if (!production) {
    return (
      <>
        <ThemedText testID="production-delegates-not-found">この公演が見つかりません。</ThemedText>
      </>
    );
  }

  if (!isPrimaryManager) {
    return (
      <>
        <ThemedText testID="production-delegates-forbidden">担当者の管理はPrimaryManagerのみ利用できます。</ThemedText>
      </>
    );
  }

  const delegates = delegatesQuery.data ?? [];

  return (
    <>
      <ThemedText type="title" style={styles.pageTitle}>
        担当者
      </ThemedText>
      <ThemedText type="small" themeColor="textSecondary" style={styles.caption}>
        この公演の仕事を任せる担当者と、その役割を管理します。
      </ThemedText>

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        現在の担当者
      </ThemedText>

      {delegatesQuery.isLoading && <ActivityIndicator testID="production-delegates-list-loading" />}
      {delegatesQuery.isError && (
        <ThemedText testID="production-delegates-list-error">{getErrorMessage(delegatesQuery.error)}</ThemedText>
      )}
      {!delegatesQuery.isLoading && !delegatesQuery.isError && delegates.length === 0 && (
        <ThemedText testID="production-delegates-empty" themeColor="textSecondary">
          担当者はまだ設定されていません。
        </ThemedText>
      )}

      {delegates.length > 0 && (
        <View style={styles.list} testID="production-delegates-list">
          {delegates.map((delegate) => (
            <View key={delegate.id} style={styles.card} testID={`production-delegate-row-${delegate.id}`}>
              <View style={styles.cardHeader}>
                <ThemedText type="smallBold">{personLabel(delegate)}</ThemedText>
                <TouchableOpacity
                  testID={`production-delegate-status-${delegate.id}`}
                  onPress={() => handleToggleStatus(delegate)}
                  disabled={updateDelegate.isPending}
                  style={[styles.statusPill, delegate.status === 'ACTIVE' ? styles.statusActive : styles.statusInactive]}
                >
                  <ThemedText type="small" style={styles.statusPillText}>
                    {delegate.status === 'ACTIVE' ? '有効' : '無効'}
                  </ThemedText>
                </TouchableOpacity>
              </View>

              <ThemedText type="small" themeColor="textSecondary">
                役割
              </ThemedText>
              <View style={styles.roleGrid}>
                {ROLES.map((role) => (
                  <TouchableOpacity
                    key={role}
                    testID={`production-delegate-role-${delegate.id}-${role}`}
                    onPress={() => handleChangeRole(delegate, role)}
                    disabled={updateDelegate.isPending}
                    style={[styles.roleButton, delegate.role === role && styles.roleButtonActive]}
                  >
                    <ThemedText type="small" style={delegate.role === role ? styles.roleButtonTextActive : undefined}>
                      {ROLE_LABEL[role] ?? role}
                    </ThemedText>
                  </TouchableOpacity>
                ))}
              </View>

              {rowErrorMessage[delegate.id] ? (
                <ThemedText testID={`production-delegate-error-${delegate.id}`} style={styles.error}>
                  {rowErrorMessage[delegate.id]}
                </ThemedText>
              ) : null}

              {confirmingDeleteId === delegate.id ? (
                <View style={styles.confirmRow} testID={`production-delegate-confirm-${delegate.id}`}>
                  <ThemedText type="small">この担当者の役割を解除しますか？</ThemedText>
                  <View style={styles.confirmButtons}>
                    <TouchableOpacity
                      testID={`production-delegate-confirm-remove-${delegate.id}`}
                      onPress={() => handleRemove(delegate)}
                      disabled={deleteDelegate.isPending}
                      style={styles.destructiveButton}
                    >
                      {deleteDelegate.isPending ? (
                        <ActivityIndicator size="small" />
                      ) : (
                        <ThemedText style={styles.destructiveButtonText}>解除する</ThemedText>
                      )}
                    </TouchableOpacity>
                    <TouchableOpacity
                      testID={`production-delegate-cancel-remove-${delegate.id}`}
                      onPress={() => setConfirmingDeleteId(null)}
                    >
                      <ThemedText type="link">キャンセル</ThemedText>
                    </TouchableOpacity>
                  </View>
                </View>
              ) : (
                <TouchableOpacity
                  testID={`production-delegate-remove-${delegate.id}`}
                  onPress={() => setConfirmingDeleteId(delegate.id)}
                  style={styles.removeLink}
                >
                  <ThemedText type="small" style={styles.destructiveText}>
                    担当を解除する
                  </ThemedText>
                </TouchableOpacity>
              )}
            </View>
          ))}
        </View>
      )}

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        担当者を追加
      </ThemedText>

      {participantsQuery.isLoading && <ActivityIndicator testID="production-delegates-candidates-loading" />}

      {personCandidates.length > 0 && (
        <View style={styles.list} testID="production-delegates-candidates">
          {personCandidates.map((participant) => (
            <TouchableOpacity
              key={participant.id}
              testID={`production-delegates-candidate-${participant.subject_id}`}
              onPress={() => setTargetPersonId(participant.subject_id)}
              style={[styles.candidateCard, targetPersonId === participant.subject_id && styles.candidateCardSelected]}
            >
              <ThemedText type="small">
                参加者（{PARTICIPANT_TYPE_LABEL[participant.participant_type] ?? participant.participant_type}）・
                表示名は現在取得できません
              </ThemedText>
              <ThemedText type="small" themeColor="textSecondary">
                ID: {participant.subject_id}
              </ThemedText>
            </TouchableOpacity>
          ))}
        </View>
      )}
      {!participantsQuery.isLoading && personCandidates.length === 0 && (
        <ThemedText testID="production-delegates-candidates-empty" type="small" themeColor="textSecondary">
          この公演にはまだ、担当者候補となる参加者（StageArtアカウントを持つ方）がいません。下のPerson IDを直接入力してください。
        </ThemedText>
      )}

      <ThemedText type="small" themeColor="textSecondary" style={styles.fieldLabel}>
        Person IDを直接入力（担当者候補一覧に見つからない場合）
      </ThemedText>
      <ThemedTextInput
        testID="production-delegates-new-person-id"
        value={targetPersonId}
        onChangeText={setTargetPersonId}
        placeholder="Person ID"
        style={styles.input}
      />

      <ThemedText type="small" themeColor="textSecondary">
        役割
      </ThemedText>
      <View style={styles.roleGrid}>
        {ROLES.map((role) => (
          <TouchableOpacity
            key={role}
            testID={`production-delegates-new-role-${role}`}
            onPress={() => setNewRole(role)}
            style={[styles.roleButton, newRole === role && styles.roleButtonActive]}
          >
            <ThemedText type="small" style={newRole === role ? styles.roleButtonTextActive : undefined}>
              {ROLE_LABEL[role] ?? role}
            </ThemedText>
          </TouchableOpacity>
        ))}
      </View>

      {addErrorMessage && (
        <ThemedText testID="production-delegates-add-error" style={styles.error}>
          {addErrorMessage}
        </ThemedText>
      )}

      <TouchableOpacity
        testID="production-delegates-add-submit"
        onPress={handleAdd}
        disabled={createDelegate.isPending || !targetPersonId.trim()}
        style={[styles.button, (createDelegate.isPending || !targetPersonId.trim()) && styles.buttonDisabled]}
      >
        {createDelegate.isPending ? <ActivityIndicator color="#fff" /> : <ThemedText style={styles.buttonText}>担当者を追加</ThemedText>}
      </TouchableOpacity>
    </>
  );
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.one },
  caption: { marginBottom: Spacing.two },
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.one },
  fieldLabel: { marginTop: Spacing.two },
  list: { gap: Spacing.two },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.one,
  },
  cardHeader: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  statusPill: { borderRadius: Radius.medium, paddingVertical: 4, paddingHorizontal: Spacing.two },
  statusActive: { backgroundColor: '#E5F3E5' },
  statusInactive: { backgroundColor: '#F0EDE8' },
  statusPillText: { fontWeight: '600' },
  roleGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.one, marginBottom: Spacing.two },
  roleButton: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.one,
    paddingHorizontal: Spacing.two,
  },
  roleButtonActive: { backgroundColor: BrandColors.warmAmber, borderColor: BrandColors.warmAmber },
  roleButtonTextActive: { color: '#fff', fontWeight: '600' },
  removeLink: { alignSelf: 'flex-start', marginTop: Spacing.one },
  destructiveText: { color: '#a6483a' },
  confirmRow: { marginTop: Spacing.one, gap: Spacing.one },
  confirmButtons: { flexDirection: 'row', alignItems: 'center', gap: Spacing.three },
  destructiveButton: {
    backgroundColor: '#a6483a',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.one,
    paddingHorizontal: Spacing.three,
  },
  destructiveButtonText: { color: '#fff', fontWeight: '600' },
  candidateCard: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: Radius.medium,
    padding: Spacing.two,
  },
  candidateCardSelected: { borderColor: BrandColors.warmAmber, backgroundColor: '#FBEFDD' },
  input: {
    borderWidth: 1,
    borderColor: '#ccc',
    borderRadius: 8,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
    fontSize: 16,
    marginBottom: Spacing.two,
  },
  error: { color: '#a6483a', marginTop: Spacing.one },
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
});
