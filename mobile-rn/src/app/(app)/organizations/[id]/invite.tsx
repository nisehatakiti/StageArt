import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useOrganizationJoinKeys } from '@/features/joinKey/useJoinKey';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt 団体管理UI整理 "メンバー管理": 参加コードを発行する/発行済み
 * コードを確認する/コードを無効にする (docs/04-DomainModel/JoinKey.md) -
 * only ever reached from members.tsx's own button, never an independent
 * Organization Context menu item. 参加申請の承認/却下はここにはもう存在し
 * ない - Homeへ移動した（HomeMembershipApprovalSection、
 * home-membership-approval.test.tsxで検証）。QR code display is
 * explicitly deferred (see the completion report) - only the underlying
 * Join Key code, which is the actual participation mechanism, is
 * implemented here.
 */
export default function OrganizationInviteScreen() {
  const { id: organizationId } = useLocalSearchParams<{ id: string }>();

  const { query: joinKeysQuery, issue, disable } = useOrganizationJoinKeys(organizationId);

  const activeKey = joinKeysQuery.data?.find((key) => key.status === 'ACTIVE');

  return (
    <>
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
      </ScrollView>
    </>
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
  primaryButton: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.three,
    paddingHorizontal: Spacing.four,
    alignItems: 'center',
  },
  primaryButtonText: { color: '#fff', fontWeight: '600' },
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
