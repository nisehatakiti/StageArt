import { useLocalSearchParams, type Href } from 'expo-router';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ApiError } from '@/api/errors';
import { WebLayout } from '@/components/web/WebLayout';
import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useCancelParticipant, useParticipants } from '@/features/participant/useParticipant';
import { useParticipationRequestDecision, usePendingParticipationRequests } from '@/features/participation/useParticipation';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import { useProduction } from '@/features/production/useProductions';
import { useProductionOrganization } from '@/features/production/useProductionOrganization';
import type { Participant } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

const PARTICIPANT_TYPE_LABEL: Record<string, string> = { CAST: '出演者', STAFF: 'スタッフ' };

/**
 * StageArt Web版 公演管理 Phase: 出演者・参加者.
 *
 * Two real, independently-backed sections, deliberately not merged into
 * one table:
 *
 * - 参加申請 (usePendingParticipationRequests/useParticipationRequestDecision,
 *   already existing hooks - GET /productions/{id}/participation-requests
 *   + POST .../approve|reject). ParticipationRequest carries real
 *   person_family_name/given_name.
 * - 現在の参加者 (new features/participant/useParticipant.ts - GET
 *   /productions/{id}/participants + DELETE /participants/{id}, freshly
 *   added this Phase since no Frontend API layer existed for the raw
 *   roster before). Filtered to ACTIVE only: a "参加申請" (Participant.md:
 *   a participation request IS a Participant row at PENDING status -
 *   confirmed by reading RequestProductionParticipationUseCase.php) would
 *   otherwise appear in both sections at once.
 *
 * ParticipantResult carries no resolved name for either Subject type
 * (ParticipantSubjectType's own docblock: callers resolve subject_id
 * themselves) - a PERSON participant therefore shows "あなた" only when
 * subject_id matches the caller's own id, otherwise its raw Person ID
 * (never a fabricated name); an ORGANIZATION participant resolves
 * through the Person's already-fetched GET /organizations list when
 * possible. Adding a participant directly by ID has no supporting
 * search-by-name endpoint (see this Phase's report's Backend
 * classification) and is not offered here - the real self-service path
 * this screen surfaces is approving a 参加申請 instead.
 */
export default function ProductionParticipantsScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const productionQuery = useProduction(id);
  const currentPersonQuery = useCurrentPerson();
  const pendingQuery = usePendingParticipationRequests(id);
  const { approve, reject } = useParticipationRequestDecision(id);
  const participantsQuery = useParticipants(id);
  const cancelParticipant = useCancelParticipant(id);

  const production = productionQuery.data;
  const { organization } = useProductionOrganization(production);
  const canManage = !!production?.is_primary_manager || production?.delegate_role === 'PARTICIPANT_MANAGER';
  const activeParticipants = (participantsQuery.data ?? []).filter((participant) => participant.status === 'ACTIVE');

  const breadcrumbs = [
    { label: 'StageArt', href: '/dashboard' as Href },
    { label: '団体', href: '/organizations' as Href },
    ...(organization ? [{ label: organization.name, href: `/organizations/${organization.id}` as Href }] : []),
    ...(organization ? [{ label: '公演', href: `/organizations/${organization.id}/productions` as Href }] : []),
    { label: production?.name ?? '...', href: `/productions/${id}` as Href },
    { label: '出演者・参加者' },
  ];

  if (productionQuery.isLoading) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" productionId={id}>
        <ActivityIndicator testID="production-participants-loading" />
      </WebLayout>
    );
  }

  if (!production) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" productionId={id}>
        <ThemedText testID="production-participants-not-found">この公演が見つかりません。</ThemedText>
      </WebLayout>
    );
  }

  if (!canManage) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" productionId={id}>
        <ThemedText testID="production-participants-forbidden">
          出演者・参加者の管理はPrimaryManagerまたは参加者管理の権限を持つ担当者のみ利用できます。
        </ThemedText>
      </WebLayout>
    );
  }

  return (
    <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" productionId={id} productionName={production.name}>
      <ThemedText type="title" style={styles.pageTitle}>
        出演者・参加者
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
        現在の参加者
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
            <ParticipantRow
              key={participant.id}
              participant={participant}
              isSelf={participant.subject_type === 'PERSON' && participant.subject_id === currentPersonQuery.data?.id}
              onCancel={() => cancelParticipant.mutate(participant.id)}
              cancelling={cancelParticipant.isPending}
            />
          ))}
        </View>
      )}
    </WebLayout>
  );
}

function ParticipantRow({
  participant,
  isSelf,
  onCancel,
  cancelling,
}: {
  participant: Participant;
  isSelf: boolean;
  onCancel: () => void;
  cancelling: boolean;
}) {
  const displayLabel =
    participant.subject_type === 'PERSON'
      ? isSelf
        ? 'あなた'
        : `Person ID: ${participant.subject_id}`
      : `Organization ID: ${participant.subject_id}`;

  return (
    <View style={styles.row} testID={`participant-row-${participant.id}`}>
      <ThemedText style={styles.colName}>{displayLabel}</ThemedText>
      <ThemedText type="small" themeColor="textSecondary" style={styles.colType}>
        {PARTICIPANT_TYPE_LABEL[participant.participant_type] ?? participant.participant_type}
      </ThemedText>
      <View style={styles.colAction}>
        <TouchableOpacity testID={`participant-cancel-${participant.id}`} onPress={onCancel} disabled={cancelling}>
          <ThemedText type="link">削除</ThemedText>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.two },
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.one },
  list: { gap: Spacing.one },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
  },
  colName: { width: 260 },
  colType: { width: 100 },
  colAction: { width: 160 },
  actionButtons: { flexDirection: 'row', gap: Spacing.two },
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
