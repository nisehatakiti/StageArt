import { useEffect } from 'react';
import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { WebLayout } from '@/components/web/WebLayout';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
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
  const isOwner = organization?.current_person_role === 'OWNER';

  // Keep OrganizationContext's own "current Organization" in sync with
  // whichever Organization's management screen is actually open - the
  // sidebar's submenu (WebLayout) and Production list both read this.
  useEffect(() => {
    if (id) {
      selectOrganization(id);
    }
  }, [id, selectOrganization]);

  if (organizationsQuery.isLoading) {
    return (
      <WebLayout breadcrumbs={[{ label: 'StageArt', href: '/dashboard' }, { label: '団体', href: '/organizations' }]} activeTopLevel="organizations">
        <ActivityIndicator testID="organization-management-loading" />
      </WebLayout>
    );
  }

  if (organizationsQuery.isError) {
    return (
      <WebLayout breadcrumbs={[{ label: 'StageArt', href: '/dashboard' }, { label: '団体', href: '/organizations' }]} activeTopLevel="organizations">
        <ThemedText testID="organization-management-error">{getErrorMessage(organizationsQuery.error)}</ThemedText>
      </WebLayout>
    );
  }

  if (!organization) {
    return (
      <WebLayout breadcrumbs={[{ label: 'StageArt', href: '/dashboard' }, { label: '団体', href: '/organizations' }]} activeTopLevel="organizations">
        <ThemedText testID="organization-management-not-found">この団体が見つからないか、参加していません。</ThemedText>
      </WebLayout>
    );
  }

  const productionCount = productionsQuery.data?.length ?? 0;
  const hasNoProductions = !productionsQuery.isLoading && !productionsQuery.isError && productionCount === 0;

  return (
    <WebLayout
      breadcrumbs={[{ label: 'StageArt', href: '/dashboard' }, { label: '団体', href: '/organizations' }, { label: organization.name }]}
      activeTopLevel="organizations"
      organizationId={id}
    >
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
          <StatusPill published={!!organization.published_at} />
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

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        団体管理
      </ThemedText>

      <View style={styles.menuGrid} testID="organization-management-menu">
        <MenuCard
          testID="organization-management-menu-edit"
          label="団体情報"
          description="名前・説明・Slugを編集"
          onPress={() => router.push(`/organizations/${id}/edit` as Href)}
          disabled={!isOwner}
        />
        <MenuCard
          testID="organization-management-menu-members"
          label="メンバー"
          description="所属メンバーの確認"
          onPress={() => router.push(`/organizations/${id}/members` as Href)}
        />
        {isOwner && (
          <MenuCard
            testID="organization-management-menu-requests"
            label="参加申請"
            description="申請の承認・却下"
            onPress={() => router.push(`/organizations/${id}/membership-requests` as Href)}
          />
        )}
        {isOwner && (
          <MenuCard
            testID="organization-management-menu-invite"
            label="招待"
            description="参加コードの発行"
            onPress={() => router.push(`/organizations/${id}/invite` as Href)}
          />
        )}
        <MenuCard
          testID="organization-management-menu-productions"
          label="公演"
          description={`${productionCount}件の公演・活動 ・ 一覧・作成へ`}
          onPress={() => router.push(`/organizations/${id}/productions` as Href)}
        />
      </View>
    </WebLayout>
  );
}

function StatusPill({ published }: { published: boolean }) {
  return (
    <View style={[styles.pill, published ? styles.pillPublished : styles.pillDraft]} testID="organization-status-pill">
      <ThemedText type="small" style={published ? styles.pillTextPublished : styles.pillTextDraft}>
        {published ? '公開中' : '下書き'}
      </ThemedText>
    </View>
  );
}

function MenuCard({
  testID,
  label,
  description,
  onPress,
  disabled,
}: {
  testID: string;
  label: string;
  description: string;
  onPress: () => void;
  disabled?: boolean;
}) {
  return (
    <TouchableOpacity testID={testID} onPress={onPress} disabled={disabled} style={[styles.menuCard, disabled && styles.menuCardDisabled]}>
      <ThemedView>
        <ThemedText type="smallBold">{label}</ThemedText>
        <ThemedText type="small" themeColor="textSecondary">
          {description}
        </ThemedText>
      </ThemedView>
    </TouchableOpacity>
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
  sectionTitle: { marginTop: Spacing.one },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: Spacing.three },
  secondaryButton: {
    borderWidth: 1,
    borderColor: '#C6892B',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
  },
  pill: { alignSelf: 'flex-start', marginTop: Spacing.one, paddingHorizontal: Spacing.two, paddingVertical: Spacing.half, borderRadius: Radius.medium },
  pillPublished: { backgroundColor: '#e3f3e8' },
  pillDraft: { backgroundColor: '#f7e4de' },
  pillTextPublished: { color: '#2f7a4a', fontWeight: '600' },
  pillTextDraft: { color: '#a6483a', fontWeight: '600' },
  menuGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.three },
  menuCard: {
    width: 220,
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    backgroundColor: '#fff',
  },
  menuCardDisabled: { opacity: 0.5 },
});
