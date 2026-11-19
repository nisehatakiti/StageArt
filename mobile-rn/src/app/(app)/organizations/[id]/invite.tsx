import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useOrganizationJoinKeys } from '@/features/joinKey/useJoinKey';
import { canManageOrganizationJoinKeys } from '@/features/organization/permissions';
import { useOrganizations } from '@/features/organization/useOrganizations';
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
 *
 * Listing / issuing / disabling a Join Key is for the Owner and a
 * delegate holding メンバー管理 on the server (List/IssueOrganizationJoinKey
 * UseCase / DisableJoinKeyUseCase), so anyone else reaching this URL is
 * told so instead of being shown the controls. If the server still
 * refuses the list (e.g. the area was removed meanwhile), its error is
 * shown and no issue button is offered.
 */
export default function OrganizationInviteScreen() {
  const { id: organizationId } = useLocalSearchParams<{ id: string }>();

  const organizationsQuery = useOrganizations();
  const organization = organizationsQuery.data?.find((candidate) => candidate.id === organizationId) ?? null;
  const allowed = canManageOrganizationJoinKeys(organization);

  const { query: joinKeysQuery, issue, disable } = useOrganizationJoinKeys(allowed ? organizationId : undefined);

  const activeKey = joinKeysQuery.data?.find((key) => key.status === 'ACTIVE');

  if (organizationsQuery.isLoading) {
    return <ActivityIndicator testID="organization-invite-loading" />;
  }

  if (!allowed) {
    return (
      <ThemedText testID="organization-invite-unavailable" themeColor="textSecondary">
        参加コードの発行・管理は、オーナーと、メンバー管理を任された代理人のみ行えます。
      </ThemedText>
    );
  }

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

          {joinKeysQuery.isError ? (
            <ThemedText testID="organization-join-key-error" style={styles.error}>
              {getErrorMessage(joinKeysQuery.error)}
            </ThemedText>
          ) : activeKey ? (
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
