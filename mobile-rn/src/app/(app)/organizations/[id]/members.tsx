import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Radius, Spacing } from '@/constants/theme';
import { useOrganizationMembers, useOrganizations } from '@/features/organization/useOrganizations';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt 団体管理UI整理 "メンバー管理": the single management hub -
 * roster (GET /organizations/{id}/members) + メンバーを追加/代理人を設定/
 * 代表者交代 entry points. OWNER and ORGANIZATION_MANAGER (代理人) can both
 * view the roster and reach メンバーを追加/代理人を設定; 代表者交代 and
 * 招待・参加コード stay reachable too (招待 is not an independent
 * Organization Context menu item - it is only ever reached from here,
 * inside メンバー管理, per this round's confirmed spec).
 */
export default function OrganizationMembersScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const organizationsQuery = useOrganizations();
  const router = useRouter();

  const organization = organizationsQuery.data?.find((candidate) => candidate.id === id) ?? null;
  const role = organization?.current_person_role ?? null;
  const isOwner = role === 'OWNER';
  const isManager = role === 'ORGANIZATION_MANAGER';
  const canManage = isOwner || isManager;

  const membersQuery = useOrganizationMembers(canManage ? id : undefined).query;

  const breadcrumbs = [
    { label: 'StageArt', href: '/dashboard' as Href },
    { label: '団体', href: '/organizations' as Href },
    { label: organization?.name ?? '...', href: `/organizations/${id}` as Href },
    { label: 'メンバー管理' },
  ];

  if (organizationsQuery.isLoading) {
    return (
      <>
        <ActivityIndicator testID="organization-members-loading" />
      </>
    );
  }

  if (organizationsQuery.isError) {
    return (
      <>
        <ThemedText testID="organization-members-error">{getErrorMessage(organizationsQuery.error)}</ThemedText>
      </>
    );
  }

  if (!organization) {
    return (
      <>
        <ThemedText testID="organization-members-not-found">この団体が見つからないか、参加していません。</ThemedText>
      </>
    );
  }

  return (
    <>
      <ThemedText type="title" style={styles.pageTitle}>
        メンバー管理
      </ThemedText>

      {canManage && (
        <View style={styles.actionsCard} testID="organization-members-actions">
          <View style={styles.actionRow}>
            <TouchableOpacity
              testID="organization-members-add"
              onPress={() => router.push(`/organizations/${id}/members/add` as Href)}
              style={styles.primaryButton}
            >
              <ThemedText style={styles.primaryButtonText}>メンバーを追加</ThemedText>
            </TouchableOpacity>
            {isOwner && (
              <TouchableOpacity
                testID="organization-members-delegate"
                onPress={() => router.push(`/organizations/${id}/members/delegate` as Href)}
                style={styles.secondaryButton}
              >
                <ThemedText style={styles.secondaryButtonText}>代理人を設定</ThemedText>
              </TouchableOpacity>
            )}
            {isOwner && (
              <TouchableOpacity
                testID="organization-members-owner-transfer"
                onPress={() => router.push(`/organizations/${id}/members/owner-transfer` as Href)}
                style={styles.secondaryButton}
              >
                <ThemedText style={styles.secondaryButtonText}>代表者交代</ThemedText>
              </TouchableOpacity>
            )}
            <TouchableOpacity
              testID="organization-members-invite"
              onPress={() => router.push(`/organizations/${id}/invite` as Href)}
              style={styles.secondaryButton}
            >
              <ThemedText style={styles.secondaryButtonText}>参加コード</ThemedText>
            </TouchableOpacity>
          </View>
        </View>
      )}

      <View style={styles.card} testID="organization-members-self-role">
        <ThemedText type="small" themeColor="textSecondary">
          あなたの役割
        </ThemedText>
        <ThemedText type="smallBold">{roleLabel(role)}</ThemedText>
      </View>

      {canManage ? (
        <>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            メンバー一覧
          </ThemedText>

          {membersQuery.isLoading && <ActivityIndicator testID="organization-members-list-loading" />}

          {membersQuery.isError && (
            <ThemedText testID="organization-members-list-error">{getErrorMessage(membersQuery.error)}</ThemedText>
          )}

          {membersQuery.data && (
            <View style={styles.list} testID="organization-members-list">
              {membersQuery.data.map((member) => (
                <View key={member.membership_id} style={styles.memberRow} testID={`organization-member-${member.person_id}`}>
                  <ThemedText type="smallBold">
                    {member.person_family_name ?? ''} {member.person_given_name ?? ''}
                  </ThemedText>
                  <ThemedText type="small" themeColor="textSecondary">
                    {roleLabel(member.role_key)}
                  </ThemedText>
                </View>
              ))}
            </View>
          )}
        </>
      ) : (
        <View style={styles.noticeCard} testID="organization-members-list-unavailable">
          <ThemedText type="small" themeColor="textSecondary">
            メンバー一覧の閲覧・管理は、オーナーまたは代理人のみ行えます。
          </ThemedText>
        </View>
      )}
    </>
  );
}

function roleLabel(roleKey: string | null): string {
  switch (roleKey) {
    case 'OWNER':
      return 'オーナー（代表者）';
    case 'ORGANIZATION_MANAGER':
      return '代理人';
    case 'MEMBER':
      return 'メンバー';
    default:
      return 'メンバー';
  }
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.four },
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.two },
  actionsCard: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.two,
    marginBottom: Spacing.three,
  },
  actionRow: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two },
  primaryButton: { backgroundColor: '#C6892B', borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.three },
  primaryButtonText: { color: '#fff', fontWeight: '600' },
  secondaryButton: { borderWidth: 1, borderColor: '#C6892B', borderRadius: Radius.medium, paddingVertical: Spacing.two, paddingHorizontal: Spacing.three },
  secondaryButtonText: { color: '#C6892B', fontWeight: '600' },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.half,
    maxWidth: 320,
    marginBottom: Spacing.three,
  },
  list: { gap: Spacing.two, maxWidth: 480 },
  memberRow: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.half,
  },
  noticeCard: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.one,
    backgroundColor: '#F7F5F1',
    maxWidth: 480,
  },
});
