import { useEffect } from 'react';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useOrganizationContext } from '@/features/organization/OrganizationContext';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { useOrganizationProductions } from '@/features/production/useProductions';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Web版 再設計 Phase 2: 団体管理トップ - the route that simply
 * did not exist before this phase (see the redesign report §12 item 1).
 * "団体を作成したら管理画面へ" only means something once this destination
 * is real; organizations/create.tsx's own post-create screen now has
 * somewhere concrete to send the user other than staying on the create
 * form or going all the way back to Home.
 */
export default function OrganizationManagementScreen() {
  const { id, saved } = useLocalSearchParams<{ id: string; saved?: string }>();
  const router = useRouter();
  const { selectOrganization } = useOrganizationContext();
  const organizationsQuery = useOrganizations();
  const productionsQuery = useOrganizationProductions(id ?? null);

  const organization = organizationsQuery.data?.find((org) => org.id === id) ?? null;

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

  const productionCount = productionsQuery.data?.length ?? 0;
  const hasNoProductions = !productionsQuery.isLoading && !productionsQuery.isError && productionCount === 0;

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
    </>
  );
}

const styles = StyleSheet.create({
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
