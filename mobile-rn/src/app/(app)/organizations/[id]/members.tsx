import { useLocalSearchParams, type Href } from 'expo-router';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { Radius, Spacing } from '@/constants/theme';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Web版 団体管理 Phase: メンバー管理.
 *
 * Deliberately does NOT render a member table: no Backend Use Case/REST
 * route exists anywhere to list an Organization's ACTIVE Memberships
 * with Person names (confirmed by reading MembershipRestController.php
 * and every Application/Membership/* Use Case - only
 * ListPendingMembershipRequestsUseCase, which explicitly filters to
 * isPending() REQUESTED rows only, and ListMyMembershipsUseCase, which
 * is scoped to the CALLER's own Memberships across every Organization,
 * never another Person's). MembershipRepositoryInterface::findByOrganizationId()
 * does exist and could back a real "list active Members" Use Case, but
 * nothing in the Application/Presentation layers exposes it today - a
 * real Backend/API gap, not a Frontend oversight. Per this Phase's own
 * instruction ("実際にAPIから取得できない情報をUI上に架空表示しないでください"),
 * this screen shows only what real data supports today: the current
 * Person's own Role in this Organization - never an invented roster.
 *
 * StageArt Organization Context Menu整理: previously also linked to
 * 参加申請/招待 (both already reachable from the Organization Context
 * left sidebar's own 参加申請/招待 items - see useNavMenu.ts) - removed
 * as duplicate in-content navigation, not as a functionality change.
 */
export default function OrganizationMembersScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const organizationsQuery = useOrganizations();

  const organization = organizationsQuery.data?.find((candidate) => candidate.id === id) ?? null;
  const isOwner = organization?.current_person_role === 'OWNER';

  const breadcrumbs = [
    { label: 'StageArt', href: '/dashboard' as Href },
    { label: '団体', href: '/organizations' as Href },
    { label: organization?.name ?? '...', href: `/organizations/${id}` as Href },
    { label: 'メンバー' },
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
        メンバー
      </ThemedText>

      <View style={styles.card} testID="organization-members-self-role">
        <ThemedText type="small" themeColor="textSecondary">
          あなたの役割
        </ThemedText>
        <ThemedText type="smallBold">{isOwner ? 'オーナー' : 'メンバー'}</ThemedText>
      </View>

      <View style={styles.noticeCard} testID="organization-members-list-unavailable">
        <ThemedText type="smallBold">メンバー一覧は現在表示できません</ThemedText>
        <ThemedText type="small" themeColor="textSecondary">
          所属メンバーの氏名・Roleを一覧取得するAPIがStageArt
          Backendにまだ実装されていないため、他のメンバーの一覧はここに表示できません。
        </ThemedText>
      </View>
    </>
  );
}

const styles = StyleSheet.create({
  pageTitle: { marginBottom: Spacing.four },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.half,
    maxWidth: 320,
    marginBottom: Spacing.three,
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
