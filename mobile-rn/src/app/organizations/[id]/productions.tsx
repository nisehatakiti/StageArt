import { useRouter, useLocalSearchParams, type Href } from 'expo-router';
import { ActivityIndicator, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { WebLayout } from '@/components/web/WebLayout';
import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { useOrganizationProductions } from '@/features/production/useProductions';
import type { Production } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

/** ProductionLifecycle.md's DRAFT/PLANNING/ACTIVE/COMPLETED/ARCHIVED,
 * plus CANCELLED - same label set as components/production-card.tsx's
 * own STATUS_LABEL (kept as a separate local copy rather than exporting
 * that component's internal constant, to avoid coupling this Web screen
 * to a mobile-only component's internals). */
const STATUS_LABEL: Record<string, string> = {
  DRAFT: '下書き',
  PLANNING: '準備中',
  ACTIVE: '進行中',
  COMPLETED: '終了',
  ARCHIVED: 'アーカイブ済み',
  CANCELLED: '中止',
};

/**
 * StageArt Web版 団体管理 Phase: 公演一覧. Reuses
 * useOrganizationProductions() unchanged (the same client-side
 * Production/Project-filtered hook organizations/index.tsx's own
 * per-row production counts already use). `/organizations/[id]/productions`
 * did not exist as a route before this Phase - only
 * `/organizations/[id]/productions/create` did (Expo Router allows a
 * leaf screen and a same-named child folder to coexist as distinct
 * routes, confirmed no collision since no `productions/index.tsx` exists).
 *
 * Deliberately omits any "開催情報" (venue/date) column: Production has
 * no such fields on the Domain today (confirmed by reading
 * Production.php/ProductionResult.php - see production-card.tsx's own
 * docblock for the same finding) - showing one here would mean
 * inventing data, which this Phase's instruction explicitly forbids.
 */
export default function OrganizationProductionsScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const organizationsQuery = useOrganizations();
  const productionsQuery = useOrganizationProductions(id ?? null);

  const organization = organizationsQuery.data?.find((candidate) => candidate.id === id) ?? null;

  const breadcrumbs = [
    { label: 'StageArt', href: '/dashboard' as Href },
    { label: '団体', href: '/organizations' as Href },
    { label: organization?.name ?? '...', href: `/organizations/${id}` as Href },
    { label: '公演' },
  ];

  if (organizationsQuery.isLoading) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" organizationId={id}>
        <ActivityIndicator testID="organization-productions-loading" />
      </WebLayout>
    );
  }

  if (!organization) {
    return (
      <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" organizationId={id}>
        <ThemedText testID="organization-productions-not-found">この団体が見つからないか、参加していません。</ThemedText>
      </WebLayout>
    );
  }

  return (
    <WebLayout breadcrumbs={breadcrumbs} activeTopLevel="organizations" organizationId={id}>
      <View style={styles.headerRow}>
        <ThemedText type="title">公演</ThemedText>
        <TouchableOpacity
          testID="organization-productions-create-link"
          onPress={() => router.push(`/organizations/${id}/productions/create` as Href)}
          style={styles.createButton}
        >
          <ThemedText style={styles.createButtonText}>＋ 公演を作成する</ThemedText>
        </TouchableOpacity>
      </View>

      {productionsQuery.isLoading && <ActivityIndicator testID="organization-productions-list-loading" />}
      {productionsQuery.isError && (
        <ThemedText testID="organization-productions-list-error">{getErrorMessage(productionsQuery.error)}</ThemedText>
      )}
      {!productionsQuery.isLoading && !productionsQuery.isError && (productionsQuery.data?.length ?? 0) === 0 && (
        <ThemedText testID="organization-productions-empty" themeColor="textSecondary">
          まだ公演・活動がありません。「公演を作成する」から最初の公演・活動を登録してください。
        </ThemedText>
      )}

      {(productionsQuery.data?.length ?? 0) > 0 && (
        <ScrollView horizontal>
          <View testID="organization-productions-table">
            <View style={styles.tableHeaderRow}>
              <ThemedText type="smallBold" style={styles.colName}>
                公演名
              </ThemedText>
              <ThemedText type="smallBold" style={styles.colStatus}>
                状態
              </ThemedText>
              <ThemedText type="smallBold" style={styles.colStatus}>
                公開状態
              </ThemedText>
              <ThemedText type="smallBold" style={styles.colAction}>
                操作
              </ThemedText>
            </View>
            {productionsQuery.data?.map((production) => (
              <ProductionRow key={production.id} production={production} organizationSlug={organization.slug} />
            ))}
          </View>
        </ScrollView>
      )}
    </WebLayout>
  );
}

function ProductionRow({ production, organizationSlug }: { production: Production; organizationSlug: string | null }) {
  const router = useRouter();
  const published = !!production.published_at;
  // Canonical root-level public URL (docs/03-PublicPageURLAndPublicationSchedule.md,
  // see [organizationSlug]/[productionSlug].tsx's own docblock) -
  // `/o/{slug}/{slug}` now only exists as a legacy redirect to this.
  const publicPath = published && organizationSlug && production.slug ? `/${organizationSlug}/${production.slug}` : null;

  return (
    <View style={styles.tableRow} testID={`organization-production-row-${production.id}`}>
      <ThemedText style={styles.colName} numberOfLines={1}>
        {production.name}
      </ThemedText>
      <ThemedText type="small" themeColor="textSecondary" style={styles.colStatus}>
        {STATUS_LABEL[production.status] ?? production.status}
      </ThemedText>
      <ThemedText type="small" themeColor="textSecondary" style={styles.colStatus}>
        {published ? '公開中' : '下書き'}
      </ThemedText>
      <View style={[styles.colAction, styles.actionLinks]}>
        <TouchableOpacity
          testID={`organization-production-manage-${production.id}`}
          onPress={() => router.push(`/production/${production.id}/schedule` as Href)}
        >
          <ThemedText type="link">管理する</ThemedText>
        </TouchableOpacity>
        {publicPath && (
          <TouchableOpacity
            testID={`organization-production-view-public-${production.id}`}
            onPress={() => router.push(publicPath as Href)}
          >
            <ThemedText type="link">公開ページ</ThemedText>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  headerRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between', marginBottom: Spacing.three },
  createButton: {
    backgroundColor: BrandColors.warmAmber,
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
  },
  createButtonText: { color: '#fff', fontWeight: '600' },
  tableHeaderRow: {
    flexDirection: 'row',
    borderBottomWidth: 1,
    borderBottomColor: '#e1dee6',
    paddingBottom: Spacing.two,
    marginBottom: Spacing.one,
  },
  tableRow: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: Spacing.two,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: '#eee',
  },
  colName: { width: 260 },
  colStatus: { width: 120 },
  colAction: { width: 200 },
  actionLinks: { flexDirection: 'row', gap: Spacing.three },
});
