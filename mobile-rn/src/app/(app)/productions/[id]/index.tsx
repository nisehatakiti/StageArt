import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Radius, Spacing } from '@/constants/theme';
import {
  canManageJoinKeys,
  canManageParticipants as canManageParticipantsOf,
  canManageProductionOperations,
  PRODUCTION_RESPONSIBILITY_LABEL,
  productionResponsibilities,
} from '@/features/production/permissions';
import { useProduction } from '@/features/production/useProductions';
import { useProductionOrganization } from '@/features/production/useProductionOrganization';
import { getErrorMessage } from '@/utils/errorMessage';

/** ProductionLifecycle.md's DRAFT/PLANNING/ACTIVE/COMPLETED/ARCHIVED,
 * plus CANCELLED - same label set already used elsewhere
 * (components/production-card.tsx, organizations/[id]/productions.tsx). */
const STATUS_LABEL: Record<string, string> = {
  DRAFT: '下書き',
  PLANNING: '準備中',
  ACTIVE: '進行中',
  COMPLETED: '終了',
  ARCHIVED: 'アーカイブ済み',
  CANCELLED: '中止',
};

/**
 * StageArt Web版 公演管理 Phase: 公演管理トップ
 * (`/productions/[id]` - previously only the mobile Production Shell's
 * `/production/[id]/...` singular routes existed, plus the onboarding
 * `/organizations/[id]/productions/create`). WebLayout's own
 * productionId prop (§5 of the original Web redesign report) already
 * expected this exact route family; this Phase is what actually builds
 * it.
 *
 * Organization is resolved client-side the same way
 * useOrganizationProductions() already does (Production has no direct
 * organization_id - see src/types/api.ts's Project docblock): fetch
 * this Production's own Project, then that Project's organization_id.
 * The 担当区分 helpers in features/production/permissions.ts (reading
 * `is_primary_manager`, `delegate_roles`, `is_proxy_manager` and
 * `can_manage_production_operations` - see ProductionAuthorizationService
 * .php's canActAsProductionManager()/canManageParticipants()) drive which
 * management cards this screen offers: 公演情報・公開設定・参加コード for
 * the PrimaryManager and the 公演代理人, 出演者・参加者 for anyone holding
 * PARTICIPANT_MANAGER. The server remains the actual authority on every
 * action behind them.
 */
export default function ProductionManagementScreen() {
  const { id, saved } = useLocalSearchParams<{ id: string; saved?: string }>();
  const router = useRouter();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const { organization } = useProductionOrganization(production);

  const canManageOperations = canManageProductionOperations(production);
  const canManageParticipants = canManageParticipantsOf(production);
  const canManageKeys = canManageJoinKeys(production);
  const responsibilities = productionResponsibilities(production);

  const breadcrumbs = [
    { label: 'StageArt', href: '/dashboard' as Href },
    { label: '団体', href: '/organizations' as Href },
    ...(organization ? [{ label: organization.name, href: `/organizations/${organization.id}` as Href }] : []),
    ...(organization ? [{ label: '公演', href: `/organizations/${organization.id}/productions` as Href }] : []),
    { label: production?.name ?? '...' },
  ];

  if (productionQuery.isLoading) {
    return (
      <>
        <ActivityIndicator testID="production-management-loading" />
      </>
    );
  }

  if (productionQuery.isError) {
    return (
      <>
        <ThemedText testID="production-management-error">{getErrorMessage(productionQuery.error)}</ThemedText>
      </>
    );
  }

  if (!production) {
    return (
      <>
        <ThemedText testID="production-management-not-found">この公演が見つかりません。</ThemedText>
      </>
    );
  }

  const published = !!production.published_at;
  // Canonical root-level public URL - see
  // [organizationSlug]/[productionSlug].tsx's own docblock (`/o/{slug}/{slug}`
  // is now only a legacy redirect to this).
  const publicPath = published && organization?.slug && production.slug ? `/${organization.slug}/${production.slug}` : null;

  return (
    <>
      {saved === '1' && (
        <View style={styles.savedBanner} testID="production-management-saved-banner">
          <ThemedText type="small" style={styles.savedBannerText}>
            保存しました
          </ThemedText>
          <TouchableOpacity testID="production-management-saved-dismiss" onPress={() => router.setParams({ saved: undefined })}>
            <ThemedText type="small" style={styles.savedBannerText}>
              ×
            </ThemedText>
          </TouchableOpacity>
        </View>
      )}

      <View style={styles.headerRow}>
        <View>
          {production.title_heading && (
            <ThemedText type="small" themeColor="textSecondary" testID="production-management-title-heading">
              {production.title_heading}
            </ThemedText>
          )}
          <ThemedText type="title" testID="production-management-name">
            {production.name}
          </ThemedText>
          <View style={styles.metaRow}>
            <StatusPill published={published} />
            <ThemedText type="small" themeColor="textSecondary">
              {STATUS_LABEL[production.status] ?? production.status}
            </ThemedText>
            {production.slug && (
              <ThemedText type="small" themeColor="textSecondary">
                Slug: {production.slug}
              </ThemedText>
            )}
          </View>
          {responsibilities.length > 0 && (
            <ThemedText type="small" themeColor="textSecondary" testID="production-management-responsibility">
              あなたの担当: {responsibilities.map((key) => PRODUCTION_RESPONSIBILITY_LABEL[key]).join('・')}
            </ThemedText>
          )}
        </View>
        {publicPath && (
          <TouchableOpacity testID="production-management-view-public" onPress={() => router.push(publicPath as Href)} style={styles.secondaryButton}>
            <ThemedText type="linkPrimary">公開ページを見る（{publicPath}）</ThemedText>
          </TouchableOpacity>
        )}
      </View>

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        公演管理
      </ThemedText>

      <View style={styles.menuGrid} testID="production-management-menu">
        <MenuCard
          testID="production-management-menu-edit"
          label="公演情報"
          description="名前・肩書・Slugを編集"
          onPress={() => router.push(`/productions/${id}/edit` as Href)}
          disabled={!canManageOperations}
        />
        <MenuCard
          testID="production-management-menu-participants"
          label="出演者・参加者"
          description="参加者の確認・参加申請の承認"
          onPress={() => router.push(`/productions/${id}/participants` as Href)}
          disabled={!canManageParticipants}
        />
        <MenuCard
          testID="production-management-menu-publish"
          label="公開設定"
          description={published ? '公開中' : '下書き（未公開）'}
          onPress={() => router.push(`/productions/${id}/publish` as Href)}
          disabled={!canManageOperations}
        />
        {canManageKeys && (
          <MenuCard
            testID="production-management-menu-invite"
            label="招待管理"
            description="参加コードの発行・確認・無効化"
            onPress={() => router.push(`/production-invite/${id}` as Href)}
          />
        )}
      </View>

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        稽古・会計・通知
      </ThemedText>

      <View style={styles.menuGrid} testID="production-management-operations-menu">
        <MenuCard
          testID="production-management-menu-schedule"
          label="稽古・出欠"
          description="稽古日程と出欠"
          onPress={() => router.push(`/production/${id}/schedule` as Href)}
        />
        <MenuCard
          testID="production-management-menu-accounting"
          label="会計"
          description="予算・実績"
          onPress={() => router.push(`/production/${id}/accounting` as Href)}
        />
        <MenuCard
          testID="production-management-menu-notifications"
          label="通知"
          description="タイムテーブル公開通知など"
          onPress={() => router.push(`/production/${id}/notifications` as Href)}
        />
      </View>
    </>
  );
}

function StatusPill({ published }: { published: boolean }) {
  return (
    <View style={[styles.pill, published ? styles.pillPublished : styles.pillDraft]} testID="production-status-pill">
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
    marginBottom: Spacing.three,
  },
  savedBannerText: { color: '#2f7a4a', fontWeight: '600' },
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', gap: Spacing.three, marginBottom: Spacing.three },
  metaRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.two, marginTop: Spacing.one, flexWrap: 'wrap' },
  secondaryButton: {
    borderWidth: 1,
    borderColor: '#C6892B',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
  },
  pill: { paddingHorizontal: Spacing.two, paddingVertical: Spacing.half, borderRadius: Radius.medium },
  pillPublished: { backgroundColor: '#e3f3e8' },
  pillDraft: { backgroundColor: '#f7e4de' },
  pillTextPublished: { color: '#2f7a4a', fontWeight: '600' },
  pillTextDraft: { color: '#a6483a', fontWeight: '600' },
  sectionTitle: { marginTop: Spacing.two, marginBottom: Spacing.one },
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
