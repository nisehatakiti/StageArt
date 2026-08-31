import { useLocalSearchParams, type Href } from 'expo-router';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ApiError } from '@/api/errors';
import { WebLayout } from '@/components/web/WebLayout';
import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { formatDayHeader } from '@/features/schedule/groupByDay';
import { useMembershipRequestDecision, usePendingMembershipRequests } from '@/features/membership/useMembership';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { getErrorMessage } from '@/utils/errorMessage';

const STATUS_LABEL: Record<string, string> = {
  REQUESTED: '申請中',
  ACTIVE: '承認済み',
  REJECTED: '却下',
};

/**
 * StageArt Web版 団体管理 Phase: 参加申請管理. Reuses exactly the same
 * usePendingMembershipRequests()/useMembershipRequestDecision() hooks
 * organizations/[id]/invite.tsx (mobile) already uses - GET
 * /organizations/{id}/membership-requests + POST .../approve|reject,
 * fully implemented server-side (MembershipRestController.php,
 * OWNER-only - ListPendingMembershipRequestsUseCase throws
 * MembershipAccessDeniedException, mapped to 403, for anyone else). This
 * is a real, fully backed list (unlike members.tsx's own Backend gap) -
 * every field shown here comes straight from MembershipRequestResult.
 */
export default function OrganizationMembershipRequestsScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const organizationsQuery = useOrganizations();
  const pendingQuery = usePendingMembershipRequests(id);
  const { approve, reject } = useMembershipRequestDecision(id);

  const organization = organizationsQuery.data?.find((candidate) => candidate.id === id) ?? null;
  const isOwner = organization?.current_person_role === 'OWNER';

  const breadcrumbs = [
    { label: 'StageArt', href: '/dashboard' as Href },
    { label: '団体', href: '/organizations' as Href },
    { label: organization?.name ?? '...', href: `/organizations/${id}` as Href },
    { label: '参加申請' },
  ];

  if (organizationsQuery.isLoading) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" organizationId={id}>
        <ActivityIndicator testID="membership-requests-loading" />
      </WebLayout>
    );
  }

  if (!organization) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" organizationId={id}>
        <ThemedText testID="membership-requests-not-found">この団体が見つからないか、参加していません。</ThemedText>
      </WebLayout>
    );
  }

  if (!isOwner) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" organizationId={id}>
        <ThemedText testID="membership-requests-forbidden">参加申請の管理はオーナーのみ利用できます。</ThemedText>
      </WebLayout>
    );
  }

  return (
    <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" organizationId={id}>
      <ThemedText type="title" style={styles.pageTitle}>
        参加申請
      </ThemedText>

      {pendingQuery.isLoading && <ActivityIndicator testID="membership-requests-list-loading" />}
      {pendingQuery.isError && (
        <ThemedText testID="membership-requests-list-error">
          {pendingQuery.error instanceof ApiError && pendingQuery.error.statusCode === 403
            ? '参加申請の管理はオーナーのみ利用できます。'
            : getErrorMessage(pendingQuery.error)}
        </ThemedText>
      )}

      {!pendingQuery.isLoading && !pendingQuery.isError && (pendingQuery.data?.length ?? 0) === 0 && (
        <ThemedText testID="membership-requests-empty" themeColor="textSecondary">
          現在、参加申請はありません。
        </ThemedText>
      )}

      {(pendingQuery.data?.length ?? 0) > 0 && (
        <View testID="membership-requests-table">
          <View style={styles.tableHeaderRow}>
            <ThemedText type="smallBold" style={styles.colName}>
              申請者
            </ThemedText>
            <ThemedText type="smallBold" style={styles.colStatus}>
              状態
            </ThemedText>
            <ThemedText type="smallBold" style={styles.colDate}>
              申請日時
            </ThemedText>
            <ThemedText type="smallBold" style={styles.colAction}>
              操作
            </ThemedText>
          </View>
          {pendingQuery.data?.map((request) => (
            <View key={request.id} style={styles.tableRow} testID={`membership-request-row-${request.id}`}>
              <ThemedText style={styles.colName}>
                {[request.person_family_name, request.person_given_name].filter(Boolean).join(' ') || '（氏名未設定）'}
              </ThemedText>
              <ThemedText type="small" themeColor="textSecondary" style={styles.colStatus}>
                {STATUS_LABEL[request.status] ?? request.status}
              </ThemedText>
              <ThemedText type="small" themeColor="textSecondary" style={styles.colDate}>
                {formatDayHeader(new Date(request.requested_at))}
              </ThemedText>
              <View style={[styles.colAction, styles.actionButtons]}>
                <TouchableOpacity
                  testID={`membership-request-approve-${request.id}`}
                  onPress={() => approve.mutate(request.id)}
                  disabled={approve.isPending || reject.isPending}
                  style={styles.approveButton}
                >
                  <ThemedText style={styles.approveButtonText}>承認</ThemedText>
                </TouchableOpacity>
                <TouchableOpacity
                  testID={`membership-request-reject-${request.id}`}
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
    </WebLayout>
  );
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.four },
  tableHeaderRow: {
    flexDirection: 'row',
    borderBottomWidth: 1,
    borderBottomColor: '#e1dee6',
    paddingBottom: Spacing.two,
    marginBottom: Spacing.one,
  },
  tableRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
  },
  colName: { width: 220 },
  colStatus: { width: 100 },
  colDate: { width: 160 },
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
