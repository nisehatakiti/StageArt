import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useOrganizationJoinKeys } from '@/features/joinKey/useJoinKey';
import { useMembershipRequestDecision, usePendingMembershipRequests } from '@/features/membership/useMembership';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * docs/04-DomainModel/JoinKey.md's Organization管理画面: 参加コードを発行
 * する/発行済みコードを確認する/コードを無効にする, plus
 * docs/04-HomeRoleBasedMenu.md's "Organization参加申請（3件）" approval
 * queue. QR code display is explicitly deferred this phase (see the
 * completion report) - only the underlying Join Key code, which is the
 * actual participation mechanism, is implemented here.
 */
export default function OrganizationInviteScreen() {
  const { id: organizationId } = useLocalSearchParams<{ id: string }>();

  const { query: joinKeysQuery, issue, disable } = useOrganizationJoinKeys(organizationId);
  const pendingQuery = usePendingMembershipRequests(organizationId);
  const { approve, reject } = useMembershipRequestDecision(organizationId);

  const activeKey = joinKeysQuery.data?.find((key) => key.status === 'ACTIVE');

  return (
    <AppShell scroll>
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText type="title" style={styles.title}>
          団体への参加
        </ThemedText>

        <ThemedView style={styles.section}>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            参加コード
          </ThemedText>

          {joinKeysQuery.isLoading && <ActivityIndicator testID="join-key-loading" />}

          {activeKey ? (
            <ThemedView style={styles.keyCard} testID="organization-join-key-active">
              <ThemedText type="title" style={styles.keyCode}>
                {activeKey.code}
              </ThemedText>
              <ThemedText type="small" themeColor="textSecondary">
                利用回数: {activeKey.use_count}回
              </ThemedText>
              <TouchableOpacity
                testID="organization-join-key-disable"
                onPress={() => disable.mutate(activeKey.id)}
                disabled={disable.isPending}
                style={styles.secondaryButton}
              >
                <ThemedText style={styles.secondaryButtonText}>このコードを無効にする</ThemedText>
              </TouchableOpacity>
            </ThemedView>
          ) : (
            !joinKeysQuery.isLoading && (
              <TouchableOpacity
                testID="organization-join-key-issue"
                onPress={() => issue.mutate()}
                disabled={issue.isPending}
                style={styles.primaryButton}
              >
                {issue.isPending ? (
                  <ActivityIndicator color="#fff" />
                ) : (
                  <ThemedText style={styles.primaryButtonText}>参加コードを発行する</ThemedText>
                )}
              </TouchableOpacity>
            )
          )}

          {issue.isError && <ThemedText style={styles.error}>{getErrorMessage(issue.error)}</ThemedText>}
        </ThemedView>

        <ThemedView style={styles.section}>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            団体参加申請
          </ThemedText>

          {pendingQuery.isLoading && <ActivityIndicator testID="pending-requests-loading" />}

          {!pendingQuery.isLoading && (pendingQuery.data?.length ?? 0) === 0 && (
            <ThemedText testID="pending-requests-empty" themeColor="textSecondary">
              現在、参加申請はありません。
            </ThemedText>
          )}

          {(pendingQuery.data?.length ?? 0) > 0 && (
            <ThemedView testID="pending-requests-list" style={styles.list}>
              {pendingQuery.data?.map((request) => (
                <ThemedView key={request.id} style={styles.requestCard} testID={`pending-request-${request.id}`}>
                  <ThemedText type="smallBold">
                    {request.person_family_name ?? ''} {request.person_given_name ?? ''}
                  </ThemedText>
                  <ThemedView style={styles.requestActions}>
                    <TouchableOpacity
                      testID={`approve-request-${request.id}`}
                      onPress={() => approve.mutate(request.id)}
                      disabled={approve.isPending || reject.isPending}
                      style={styles.approveButton}
                    >
                      <ThemedText style={styles.primaryButtonText}>承認</ThemedText>
                    </TouchableOpacity>
                    <TouchableOpacity
                      testID={`reject-request-${request.id}`}
                      onPress={() => reject.mutate(request.id)}
                      disabled={approve.isPending || reject.isPending}
                      style={styles.secondaryButton}
                    >
                      <ThemedText style={styles.secondaryButtonText}>却下</ThemedText>
                    </TouchableOpacity>
                  </ThemedView>
                </ThemedView>
              ))}
            </ThemedView>
          )}
        </ThemedView>
      </ScrollView>
    </AppShell>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.four },
  title: { fontSize: 22, lineHeight: 28 },
  section: { gap: Spacing.two },
  sectionTitle: { marginBottom: Spacing.one },
  keyCard: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    padding: Spacing.four,
    alignItems: 'center',
    gap: Spacing.two,
  },
  keyCode: { letterSpacing: 4 },
  list: { gap: Spacing.two },
  requestCard: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.two,
  },
  requestActions: { flexDirection: 'row', gap: Spacing.two },
  primaryButton: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.three,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
  },
  primaryButtonText: { color: '#fff', fontWeight: '600' },
  approveButton: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
    alignItems: 'center',
  },
  secondaryButton: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
    alignItems: 'center',
  },
  secondaryButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  error: { color: '#a6483a' },
});
