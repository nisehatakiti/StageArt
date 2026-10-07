import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useOrganizationMembers, useOrganizations } from '@/features/organization/useOrganizations';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt 団体管理UI整理 "代理人を設定": per docs/04-DomainModel/
 * Organization.md's "DelegateRole" section ("Organization Ownerという
 * 独立したRole体系は作成しない"), this is Membership::changeRole() toward/
 * away from RoleKey::ORGANIZATION_MANAGER - no separate Delegate Entity
 * (unlike Production's ProductionDelegate). Owner-only (see
 * AssignOrganizationDelegateUseCase/RevokeOrganizationDelegateUseCase).
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
    return (
      <ThemedText testID="organization-members-delegate-forbidden">
        代理人の設定はオーナーのみ行えます。
      </ThemedText>
    );
  }

  const others = (membersQuery.data ?? []).filter((member) => member.role_key !== 'OWNER');

  return (
    <ScrollView contentContainerStyle={styles.container}>
      <ThemedText type="title" style={styles.pageTitle}>
        代理人を設定
      </ThemedText>
      <ThemedText type="small" themeColor="textSecondary" style={styles.lead}>
        代理人に設定したメンバーは、団体情報の編集・メンバーの追加など、オーナーに近い管理操作を行えるようになります。
      </ThemedText>

      {membersQuery.isError && (
        <ThemedText testID="organization-members-delegate-error">{getErrorMessage(membersQuery.error)}</ThemedText>
      )}

      <View style={styles.list} testID="organization-members-delegate-list">
        {others.length === 0 && (
          <ThemedText type="small" themeColor="textSecondary" testID="organization-members-delegate-empty">
            代理人に設定できるメンバーがいません。先に「メンバーを追加」してください。
          </ThemedText>
        )}

        {others.map((member) => {
          const isDelegate = member.role_key === 'ORGANIZATION_MANAGER';

          return (
            <View key={member.membership_id} style={styles.row} testID={`organization-delegate-row-${member.person_id}`}>
              <View style={styles.rowInfo}>
                <ThemedText type="smallBold">
                  {member.person_family_name ?? ''} {member.person_given_name ?? ''}
                </ThemedText>
                <ThemedText type="small" themeColor="textSecondary">
                  {isDelegate ? '代理人' : 'メンバー'}
                </ThemedText>
              </View>

              {isDelegate ? (
                <TouchableOpacity
                  testID={`organization-delegate-revoke-${member.person_id}`}
                  onPress={() => revokeDelegate.mutate(member.person_id)}
                  disabled={revokeDelegate.isPending || assignDelegate.isPending}
                  style={styles.secondaryButton}
                >
                  <ThemedText style={styles.secondaryButtonText}>解除する</ThemedText>
                </TouchableOpacity>
              ) : (
                <TouchableOpacity
                  testID={`organization-delegate-assign-${member.person_id}`}
                  onPress={() => assignDelegate.mutate(member.person_id)}
                  disabled={revokeDelegate.isPending || assignDelegate.isPending}
                  style={styles.primaryButton}
                >
                  <ThemedText style={styles.primaryButtonText}>代理人にする</ThemedText>
                </TouchableOpacity>
              )}
            </View>
          );
        })}
      </View>

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
  container: { padding: Spacing.four, gap: Spacing.two },
  pageTitle: { marginBottom: Spacing.one },
  lead: { marginBottom: Spacing.three, maxWidth: 480 },
  list: { gap: Spacing.two, maxWidth: 480 },
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
  rowInfo: { gap: Spacing.half },
  primaryButton: { backgroundColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.three },
  primaryButtonText: { color: '#fff', fontWeight: '600' },
  secondaryButton: { borderWidth: 1, borderColor: BrandColors.warmAmber, borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.three },
  secondaryButtonText: { color: BrandColors.warmAmber, fontWeight: '600' },
  error: { color: '#a6483a' },
  backLink: { marginTop: Spacing.three },
});
