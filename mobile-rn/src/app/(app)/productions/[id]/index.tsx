import { useLocalSearchParams, useRouter, type Href } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Radius, Spacing } from '@/constants/theme';
import {
  useActivateProduction,
  useArchiveProduction,
  useCancelProduction,
  useCompleteProduction,
  useProduction,
  useStartProductionPlanning,
} from '@/features/production/useProductions';
import { useProductionOrganization } from '@/features/production/useProductionOrganization';
import { confirmAlert } from '@/utils/confirmAlert';
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
 * `is_primary_manager`/`delegate_role` (already on every Production
 * fetch - see ProductionAuthorizationService.php's canManageProduction()/
 * canManageParticipants()) drive which management cards this screen
 * offers; the server remains the actual authority on every action
 * behind them.
 */
export default function ProductionManagementScreen() {
  const { id, saved } = useLocalSearchParams<{ id: string; saved?: string }>();
  const router = useRouter();
  const productionQuery = useProduction(id);
  const production = productionQuery.data;
  const { organization } = useProductionOrganization(production);

  const isPrimaryManager = !!production?.is_primary_manager;
  const canManageParticipants = isPrimaryManager || production?.delegate_role === 'PARTICIPANT_MANAGER';
  const canManagePerformances = isPrimaryManager || production?.delegate_role === 'PERFORMANCE_MANAGER';
  const canManageTickets = isPrimaryManager || production?.delegate_role === 'TICKET_MANAGER';
  const canManageCheckIn = isPrimaryManager || production?.delegate_role === 'CHECKIN_MANAGER';

  const [lifecycleError, setLifecycleError] = useState<string | null>(null);
  const startPlanning = useStartProductionPlanning(id);
  const activate = useActivateProduction(id);
  const complete = useCompleteProduction(id);
  const archive = useArchiveProduction(id);
  const cancel = useCancelProduction(id);
  const lifecycleBusy = startPlanning.isPending || activate.isPending || complete.isPending || archive.isPending || cancel.isPending;

  async function runLifecycleAction(mutation: { mutateAsync: () => Promise<unknown> }) {
    setLifecycleError(null);
    try {
      await mutation.mutateAsync();
    } catch (error) {
      setLifecycleError(getErrorMessage(error));
    }
  }

  function confirmAndRun(title: string, message: string, mutation: { mutateAsync: () => Promise<unknown> }) {
    confirmAlert(title, message, [
      { text: 'キャンセル', style: 'cancel' },
      { text: 'OK', style: 'default', onPress: () => runLifecycleAction(mutation) },
    ]);
  }

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
        </View>
        {publicPath && (
          <TouchableOpacity testID="production-management-view-public" onPress={() => router.push(publicPath as Href)} style={styles.secondaryButton}>
            <ThemedText type="linkPrimary">公開ページを見る（{publicPath}）</ThemedText>
          </TouchableOpacity>
        )}
      </View>

      {isPrimaryManager && (production.status === 'DRAFT' || production.status === 'PLANNING' || production.status === 'ACTIVE' || production.status === 'COMPLETED') && (
        <View style={styles.lifecycleRow} testID="production-management-lifecycle-actions">
          {lifecycleError && (
            <ThemedText testID="production-management-lifecycle-error" style={styles.lifecycleError}>
              {lifecycleError}
            </ThemedText>
          )}
          {production.status === 'DRAFT' && (
            <TouchableOpacity
              testID="production-management-start-planning"
              onPress={() => runLifecycleAction(startPlanning)}
              disabled={lifecycleBusy}
              style={[styles.secondaryButton, lifecycleBusy && styles.menuCardDisabled]}
            >
              <ThemedText type="linkPrimary">企画を開始する</ThemedText>
            </TouchableOpacity>
          )}
          {production.status === 'PLANNING' && (
            <TouchableOpacity
              testID="production-management-activate"
              onPress={() => runLifecycleAction(activate)}
              disabled={lifecycleBusy}
              style={[styles.secondaryButton, lifecycleBusy && styles.menuCardDisabled]}
            >
              <ThemedText type="linkPrimary">制作を開始する</ThemedText>
            </TouchableOpacity>
          )}
          {production.status === 'ACTIVE' && (
            <TouchableOpacity
              testID="production-management-complete"
              onPress={() =>
                confirmAndRun(
                  '公演終了・精算完了',
                  'この公演を終了し、決算完了（COMPLETED）にします。未精算のメンバーが残っている場合はエラーになります。よろしいですか？',
                  complete
                )
              }
              disabled={lifecycleBusy}
              style={[styles.secondaryButton, lifecycleBusy && styles.menuCardDisabled]}
            >
              <ThemedText type="linkPrimary">公演を終了する（決算完了）</ThemedText>
            </TouchableOpacity>
          )}
          {production.status === 'COMPLETED' && (
            <TouchableOpacity
              testID="production-management-archive"
              onPress={() => confirmAndRun('アーカイブ', 'この公演をアーカイブします。よろしいですか？', archive)}
              disabled={lifecycleBusy}
              style={[styles.secondaryButton, lifecycleBusy && styles.menuCardDisabled]}
            >
              <ThemedText type="linkPrimary">アーカイブする</ThemedText>
            </TouchableOpacity>
          )}
          {(production.status === 'DRAFT' || production.status === 'PLANNING' || production.status === 'ACTIVE') && (
            <TouchableOpacity
              testID="production-management-cancel"
              onPress={() => confirmAndRun('公演の中止', 'この公演を中止します。この操作は取り消せません。よろしいですか？', cancel)}
              disabled={lifecycleBusy}
              style={[styles.secondaryButton, lifecycleBusy && styles.menuCardDisabled]}
            >
              <ThemedText style={styles.destructiveText}>この公演を中止する</ThemedText>
            </TouchableOpacity>
          )}
        </View>
      )}

      <ThemedText type="subtitle" style={styles.sectionTitle}>
        公演管理
      </ThemedText>

      <View style={styles.menuGrid} testID="production-management-menu">
        <MenuCard
          testID="production-management-menu-edit"
          label="公演情報"
          description="名前・肩書・Slugを編集"
          onPress={() => router.push(`/productions/${id}/edit` as Href)}
          disabled={!isPrimaryManager}
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
          disabled={!isPrimaryManager}
        />
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
          testID="production-management-menu-performances"
          label="公演回管理"
          description="公演回の一覧・作成・編集"
          onPress={() => router.push(`/productions/${id}/performances` as Href)}
          disabled={!canManagePerformances}
        />
        <MenuCard
          testID="production-management-menu-tickets"
          label="チケット管理"
          description="チケット設定・ノルマ・チケットバック"
          onPress={() => router.push(`/productions/${id}/tickets` as Href)}
          disabled={!canManageTickets}
        />
        <MenuCard
          testID="production-management-menu-checkin"
          label="受付（Check-in）"
          description="来場受付・当日券"
          onPress={() => router.push(`/productions/${id}/checkin` as Href)}
          disabled={!canManageCheckIn}
        />
        <MenuCard
          testID="production-management-menu-settlement"
          label="精算"
          description="チケットバックの精算"
          onPress={() => router.push(`/productions/${id}/settlement` as Href)}
          disabled={!isPrimaryManager}
        />
        <MenuCard
          testID="production-management-menu-member-performance-summary"
          label="メンバー実績サマリー"
          description="稽古出欠・チケット販売実績"
          onPress={() => router.push(`/productions/${id}/member-performance-summary` as Href)}
          disabled={!isPrimaryManager}
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
  lifecycleRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: Spacing.two, marginBottom: Spacing.three },
  lifecycleError: { color: '#a6483a', width: '100%' },
  destructiveText: { color: '#a6483a', fontWeight: '600' },
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
