import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ManagementCard } from '@/components/management-card';
import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useOrganizationMembers, useOrganizations } from '@/features/organization/useOrganizations';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Organization管理UI/UX統一 "代理人を設定": per
 * docs/04-DomainModel/Organization.md's "DelegateRole" section
 * ("Organization Ownerという独立したRole体系は作成しない"), this stays
 * Membership::changeRole() toward/away from RoleKey::ORGANIZATION_MANAGER
 * - no separate Delegate Entity. The screen itself is restructured per
 * this round's §6: 現在の代理人 is shown as its own summary (not just a
 * per-row label buried in the candidate list), 権限内容 is spelled out
 * explicitly, and 設定/解除 stay deliberate actions - never a bare role
 * toggle on the main メンバー管理 table.
 */
export default function OrganizationMembersDelegateScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const organizationsQuery = useOrganizations();
  const organization = organizationsQuery.data?.find((candidate) => candidate.id === id) ?? null;
  const isOwner = organization?.current_person_role === 'OWNER';

  const { query: membersQuery, assignDelegate, revokeDelegate } = useOrganizationMembers(isOwner ? id : undefined);

  if (organizationsQuery.isLoading || membersQuery.isLoading) {
    return <ActivityIndicator testID="organization-members-delegate-loading" />;
  }

  if (!organization) {
    return <ThemedText testID="organization-members-delegate-not-found">この団体が見つからないか、参加していません。</ThemedText>;
  }

  if (!isOwner) {
    return <ThemedText testID="organization-members-delegate-forbidden">代理人の設定はオーナーのみ行えます。</ThemedText>;
  }

  const currentDelegates = (membersQuery.data ?? []).filter((member) => member.role_key === 'ORGANIZATION_MANAGER');
  const candidates = (membersQuery.data ?? []).filter((member) => member.role_key === 'MEMBER');

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <ThemedText type="title" style={styles.pageTitle}>
        代理人を設定
      </ThemedText>

      <ManagementCard title="権限内容" description="代理人は、オーナーに近い次の管理操作を行えます。">
        <ThemedText type="small" themeColor="textSecondary">
          ・団体情報（基本情報・団体ロゴ・公開設定・SNS・外部リンク）の編集
        </ThemedText>
        <ThemedText type="small" themeColor="textSecondary">
          ・メンバーの追加、備考の編集
        </ThemedText>
        <ThemedText type="small" themeColor="textSecondary" style={styles.permissionCaveat}>
          ※代表者交代、代理人自身の設定・解除はオーナーのみ行えます。
        </ThemedText>
      </ManagementCard>

      <ManagementCard title="現在の代理人">
        {currentDelegates.length === 0 ? (
          <ThemedText type="small" themeColor="textSecondary" testID="organization-members-delegate-current-empty">
            現在、代理人は設定されていません。
          </ThemedText>
        ) : (
          <View style={styles.list} testID="organization-members-delegate-current-list">
            {currentDelegates.map((member) => (
              <View key={member.membership_id} style={styles.row} testID={`organization-delegate-current-${member.person_id}`}>
                <ThemedText type="smallBold">
                  {member.person_family_name ?? ''} {member.person_given_name ?? ''}
                </ThemedText>
                <TouchableOpacity
                  testID={`organization-delegate-revoke-${member.person_id}`}
                  onPress={() => revokeDelegate.mutate(member.person_id)}
                  disabled={revokeDelegate.isPending || assignDelegate.isPending}
                  style={styles.secondaryButton}
                >
                  <ThemedText style={styles.secondaryButtonText}>解除する</ThemedText>
                </TouchableOpacity>
              </View>
            ))}
          </View>
        )}
      </ManagementCard>

      <ManagementCard title="代理人にするメンバー">
        {membersQuery.isError && (
          <ThemedText testID="organization-members-delegate-error">{getErrorMessage(membersQuery.error)}</ThemedText>
        )}

        <View style={styles.list} testID="organization-members-delegate-list">
          {candidates.length === 0 && (
            <ThemedText type="small" themeColor="textSecondary" testID="organization-members-delegate-empty">
              代理人に設定できるメンバーがいません。先に「メンバーを追加」してください。
            </ThemedText>
          )}

          {candidates.map((member) => (
            <View key={member.membership_id} style={styles.row} testID={`organization-delegate-row-${member.person_id}`}>
              <ThemedText type="smallBold">
                {member.person_family_name ?? ''} {member.person_given_name ?? ''}
              </ThemedText>
              <TouchableOpacity
                testID={`organization-delegate-assign-${member.person_id}`}
                onPress={() => assignDelegate.mutate(member.person_id)}
                disabled={revokeDelegate.isPending || assignDelegate.isPending}
                style={styles.primaryButton}
              >
                <ThemedText style={styles.primaryButtonText}>代理人にする</ThemedText>
              </TouchableOpacity>
            </View>
          ))}
        </View>
      </ManagementCard>

      {(assignDelegate.isError || revokeDelegate.isError) && (
        <ThemedText testID="organization-members-delegate-action-error" style={styles.error}>
          {getErrorMessage(assignDelegate.error ?? revokeDelegate.error)}
        </ThemedText>
      )}

      <TouchableOpacity testID="organization-members-delegate-back" onPress={() => router.push(`/organizations/${id}/members` as Href)} style={styles.backLink}>
        <ThemedText type="link">メンバー管理へ戻る</ThemedText>
      </TouchableOpacity>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  container: { padding: Spacing.four, gap: Spacing.one, paddingBottom: Spacing.six },
  pageTitle: { marginBottom: Spacing.three },
  permissionCaveat: { marginTop: Spacing.one },
  list: { gap: Spacing.two },
  row: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: Spacing.two,
  },
  primaryButton: { backgroundColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.three },
  primaryButtonText: { color: '#fff', fontWeight: '600' },
  secondaryButton: { borderWidth: 1, borderColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.three },
  secondaryButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  error: { color: '#a6483a' },
  backLink: { marginTop: Spacing.three },
});
