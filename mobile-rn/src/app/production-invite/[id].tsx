import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { AppShell } from '@/components/app-shell';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useProductionJoinKeys } from '@/features/joinKey/useJoinKey';
import { useParticipationRequestDecision, usePendingParticipationRequests } from '@/features/participation/useParticipation';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Web β版: Production招待管理 (Join Key発行 + 参加申請承認),
 * mirroring organizations/[id]/invite.tsx at the Production Scope.
 * Deliberately a top-level route (`/production-invite/{id}`), NOT nested
 * under `production/[id]/` - that directory's own _layout.tsx renders a
 * native <Tabs> navigator with exactly 4 declared Tabs.Screen children
 * (schedule/notifications/accounting/mypage); placing a 5th sibling file
 * there risks Expo Router auto-registering it as an actual extra native
 * tab (undocumented/ambiguous behavior - see this Phase's report), which
 * would change the existing, already-tested native tab bar. This route
 * sits entirely outside that Tabs subtree instead, reached only via the
 * Web-only link in production/[id]/_layout.tsx's header.
 *
 * QR code display is explicitly deferred this phase - see this
 * screen's Organization counterpart's docblock and this Phase's
 * completion report for the reasoning.
 */
export default function ProductionInviteScreen() {
  const { id: productionId } = useLocalSearchParams<{ id: string }>();

  const { query: joinKeysQuery, issue, disable } = useProductionJoinKeys(productionId);
  const pendingQuery = usePendingParticipationRequests(productionId);
  const { approve, reject } = useParticipationRequestDecision(productionId);

  const activeKey = joinKeysQuery.data?.find((key) => key.status === 'ACTIVE');

  return (
    <AppShell scroll>
      <ScrollView contentContainerStyle={styles.container}>
        <ThemedText type="title" style={styles.title}>
          公演・活動への参加
        </ThemedText>

        <ThemedView style={styles.section}>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            参加コード
          </ThemedText>

          {joinKeysQuery.isLoading && <ActivityIndicator testID="production-join-key-loading" />}

          {activeKey ? (
            <ThemedView style={styles.keyCard} testID="production-join-key-active">
              <ThemedText type="title" style={styles.keyCode}>
                {activeKey.code}
              </ThemedText>
              <ThemedText type="small" themeColor="textSecondary">
                利用回数: {activeKey.use_count}回
              </ThemedText>
              <TouchableOpacity
                testID="production-join-key-disable"
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
                testID="production-join-key-issue"
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
            公演・活動参加申請
          </ThemedText>

          {pendingQuery.isLoading && <ActivityIndicator testID="pending-participation-loading" />}

          {!pendingQuery.isLoading && (pendingQuery.data?.length ?? 0) === 0 && (
            <ThemedText testID="pending-participation-empty" themeColor="textSecondary">
              現在、参加申請はありません。
            </ThemedText>
          )}

          {(pendingQuery.data?.length ?? 0) > 0 && (
            <ThemedView testID="pending-participation-list" style={styles.list}>
              {pendingQuery.data?.map((request) => (
                <ThemedView key={request.id} style={styles.requestCard} testID={`pending-participation-${request.id}`}>
                  <ThemedText type="smallBold">
                    {request.person_family_name ?? ''} {request.person_given_name ?? ''}
                    {'　'}
                    {request.participant_type === 'CAST' ? '(出演者)' : '(スタッフ)'}
                  </ThemedText>
                  <ThemedView style={styles.requestActions}>
                    <TouchableOpacity
                      testID={`approve-participation-${request.id}`}
                      onPress={() => approve.mutate(request.id)}
                      disabled={approve.isPending || reject.isPending}
                      style={styles.approveButton}
                    >
                      <ThemedText style={styles.primaryButtonText}>承認</ThemedText>
                    </TouchableOpacity>
                    <TouchableOpacity
                      testID={`reject-participation-${request.id}`}
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
