import { useEffect } from 'react';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useOrganizationContext } from '@/features/organization/OrganizationContext';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { usePendingMembershipRequests } from '@/features/membership/useMembership';
import { useOrganizationProductions } from '@/features/production/useProductions';
import { getErrorMessage } from '@/utils/errorMessage';

const PRODUCTION_STATUS_LABEL: Record<string, string> = {
  PLANNING: '準備中',
  ACTIVE: '進行中',
  COMPLETED: '終了',
  ARCHIVED: 'アーカイブ済み',
  CANCELLED: '中止',
};

/**
 * StageArt Web版 再設計 Phase 2: 団体管理トップ - the route that simply
 * did not exist before this phase (see the redesign report §12 item 1).
 * "団体を作成したら管理画面へ" only means something once this destination
 * is real; organizations/create.tsx's own post-create screen now has
 * somewhere concrete to send the user other than staying on the create
 * form or going all the way back to Home.
 *
 * StageArt UI再構成 instruction (this round §「Organization Context」):
 * "現在の「団体管理」カード画面をそのまま正解とみなさない" - this screen
 * previously only showed name/description/a "no productions yet" banner,
 * with no real Dashboard content. Added: current Role (already-fetched
 * `organization.current_person_role`), the actual Production list
 * (reusing `useOrganizationProductions()`, already used by the sidebar's
 * own 公演一覧 screen), and - Owner only, matching the existing gating on
 * 参加申請/招待 in useNavMenu.ts - the pending membership request count
 * (reusing `usePendingMembershipRequests()`, already used by invite.tsx).
 * No new API call was added; all of this data was already fetched
 * elsewhere in the app for the same Organization.
 */
export default function OrganizationManagementScreen() {
  const { id, saved } = useLocalSearchParams<{ id: string; saved?: string }>();
  const router = useRouter();
  const { selectOrganization } = useOrganizationContext();
  const organizationsQuery = useOrganizations();
  const productionsQuery = useOrganizationProductions(id ?? null);

  const organization = organizationsQuery.data?.find((org) => org.id === id) ?? null;
  const isOwner = organization?.current_person_role === 'OWNER';
  const pendingRequestsQuery = usePendingMembershipRequests(isOwner ? id : undefined);

  // Keep OrganizationContext's own "current Organization" in sync with
  // whichever Organization's management screen is actually open - the
  // admin nav menu (useNavMenu) and Production list both read this.
  useEffect(() => {
    if (id) {
      selectOrganization(id);
    }
  }, [id, selectOrganization]);

  if (organizationsQuery.isLoading) {
    return (
      <>
        <ActivityIndicator testID="organization-management-loading" />
      </>
    );
  }

  if (organizationsQuery.isError) {
    return (
      <>
        <ThemedText testID="organization-management-error">{getErrorMessage(organizationsQuery.error)}</ThemedText>
      </>
    );
  }

  if (!organization) {
    return (
      <>
        <ThemedText testID="organization-management-not-found">この団体が見つからないか、参加していません。</ThemedText>
      </>
    );
  }

  const productions = productionsQuery.data ?? [];
  const hasNoProductions = !productionsQuery.isLoading && !productionsQuery.isError && productions.length === 0;
  const pendingCount = pendingRequestsQuery.data?.length ?? 0;

  return (
    <>
      {saved === '1' && (
        <View style={styles.savedBanner} testID="organization-management-saved-banner">
          <ThemedText type="small" style={styles.savedBannerText}>
            保存しました
          </ThemedText>
          <TouchableOpacity testID="organization-management-saved-dismiss" onPress={() => router.setParams({ saved: undefined })}>
            <ThemedText type="small" style={styles.savedBannerText}>
              ×
            </ThemedText>
          </TouchableOpacity>
        </View>
      )}

      <View style={styles.headerRow}>
        <View>
          <ThemedText type="title" testID="organization-management-name">
            {organization.name}
          </ThemedText>
          <ThemedText type="small" themeColor="textSecondary" testID="organization-management-role">
            {isOwner ? 'オーナー' : 'メンバー'}
          </ThemedText>
        </View>
        {organization.published_at && organization.slug && (
          <TouchableOpacity
            testID="organization-management-view-public"
            onPress={() => router.push(`/${organization.slug}` as Href)}
            style={styles.secondaryButton}
          >
            <ThemedText type="linkPrimary">公開ページを見る（/{organization.slug}）</ThemedText>
          </TouchableOpacity>
        )}
      </View>

      {organization.description && <ThemedText themeColor="textSecondary">{organization.description}</ThemedText>}

      {isOwner && pendingCount > 0 && (
        <TouchableOpacity
          testID="organization-management-pending-requests"
          onPress={() => router.push(`/organizations/${id}/membership-requests` as Href)}
          style={styles.nextStepBanner}
        >
          <ThemedText type="smallBold">参加申請が{pendingCount}件あります</ThemedText>
          <ThemedText type="small" themeColor="textSecondary">
            確認する
          </ThemedText>
        </TouchableOpacity>
      )}

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        公演・活動
      </ThemedText>

      {hasNoProductions && (
        <View style={styles.nextStepBanner} testID="organization-management-no-productions">
          <ThemedText type="smallBold">まだ公演・活動がありません</ThemedText>
          <ThemedText type="small" themeColor="textSecondary">
            「公演を作成する」から最初の公演・活動を登録してください。
          </ThemedText>
          <TouchableOpacity
            testID="organization-management-create-production"
            onPress={() => router.push(`/organizations/${id}/productions/create` as Href)}
            style={styles.primaryButton}
          >
            <ThemedText style={styles.primaryButtonText}>＋ 公演を作成する</ThemedText>
          </TouchableOpacity>
        </View>
      )}

      {productions.length > 0 && (
        <View style={styles.list} testID="organization-management-productions">
          {productions.map((production) => (
            <TouchableOpacity
              key={production.id}
              testID={`organization-management-production-${production.id}`}
              style={styles.productionRow}
              onPress={() => router.push(`/production/${production.id}/schedule` as Href)}
            >
              <ThemedText type="smallBold">{production.name}</ThemedText>
              <ThemedText type="small" themeColor="textSecondary">
                {PRODUCTION_STATUS_LABEL[production.status] ?? production.status}
              </ThemedText>
            </TouchableOpacity>
          ))}
        </View>
      )}
    </>
  );
}

const styles = StyleSheet.create({
  sectionTitle: { marginTop: Spacing.three, marginBottom: Spacing.one },
  list: { gap: Spacing.one },
  productionRow: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: 2,
  },
  savedBanner: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: '#e3f3e8',
    borderRadius: Radius.medium,
    paddingHorizontal: Spacing.three,
    paddingVertical: Spacing.two,
  },
  savedBannerText: { color: '#2f7a4a', fontWeight: '600' },
  nextStepBanner: {
    borderWidth: 1,
    borderColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.one,
    alignItems: 'flex-start',
  },
  primaryButton: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
    marginTop: Spacing.one,
  },
  primaryButtonText: { color: '#fff', fontWeight: '600' },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: Spacing.three },
  secondaryButton: {
    borderWidth: 1,
    borderColor: '#C6892B',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
  },
});
