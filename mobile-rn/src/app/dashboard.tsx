import { useRouter, type Href } from 'expo-router';
import { ActivityIndicator, StyleSheet, TouchableOpacity, View } from 'react-native';

import { WebLayout } from '@/components/web/WebLayout';
import { ThemedText } from '@/components/themed-text';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import {
  buildDashboardViewModel,
  type DashboardNotificationViewModel,
  type FollowedOrganizationFeedItemViewModel,
  type UpcomingRehearsalViewModel,
} from '@/features/dashboard/viewModel';
import { useMyDashboard } from '@/features/dashboard/useDashboard';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { useOrganizationProductions } from '@/features/production/useProductions';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import type { Organization } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

/**
 * StageArt Web版 再設計 Phase 2続き: the Web-only "ホーム/ダッシュボード"
 * WebLayout's own top-level nav and header logo already pointed here
 * (see WebLayout.tsx's TOP_LEVEL_ITEMS) - this is that destination, not
 * a port of home.tsx's mobile screen. Same underlying data
 * (useMyDashboard/buildDashboardViewModel, useOrganizations,
 * useOrganizationProductions - all unchanged) but laid out as a PC-width
 * two-column overview instead of a single scrolling mobile column: the
 * explicit instruction for this redesign is "モバイル版のWeb移植ではなく
 * 独立したUI", which cuts against reusing home.tsx's own JSX/StyleSheet,
 * not against reusing its already-proven data layer.
 *
 * Organization/Production management entry points (a Person's own
 * Organizations, each with its Production count) always render here,
 * unlike home.tsx's §02.6 gate on real Membership - the Web Dashboard IS
 * reached via WebLayout's persistent sidebar (団体/公演・活動/プロフィール
 * are always one click away regardless), so this screen's own job is
 * "何ができるか一目でわかる管理者向けの起点", not the mobile Home's
 * Person-first onboarding surface for a possibly-unaffiliated user.
 */
export default function DashboardScreen() {
  const router = useRouter();
  const currentPersonQuery = useCurrentPerson();
  const dashboardQuery = useMyDashboard();
  const organizationsQuery = useOrganizations();
  const dashboardViewModel = dashboardQuery.data ? buildDashboardViewModel(dashboardQuery.data) : null;

  const familyName = currentPersonQuery.data?.family_name;

  return (
    <WebLayout breadcrumbs={[{ label: 'Dashboard' }]} activeTopLevel="dashboard">
      <View style={styles.headerRow}>
        <View>
          <ThemedText type="title" testID="dashboard-greeting">
            {familyName ? `${greetingForCurrentTime()}、${familyName}さん` : 'Dashboard'}
          </ThemedText>
          {currentPersonQuery.data && !currentPersonQuery.data.email_verified && (
            <ThemedText type="small" themeColor="textSecondary" testID="dashboard-email-unverified">
              メールアドレスが未確認です。
              <ThemedText type="link" onPress={() => router.push('/profile')}>
                プロフィールで確認する
              </ThemedText>
            </ThemedText>
          )}
        </View>
      </View>

      <View style={styles.quickActions} testID="dashboard-quick-actions">
        <QuickAction testID="dashboard-quick-action-create-organization" label="＋ 団体を作る" primary onPress={() => router.push('/organizations/create')} />
        <QuickAction testID="dashboard-quick-action-discover-organizations" label="団体を探す" onPress={() => router.push('/discover-organizations')} />
        <QuickAction testID="dashboard-quick-action-discover-productions" label="公演・活動を探す" onPress={() => router.push('/discover-productions')} />
        <QuickAction testID="dashboard-quick-action-join" label="参加コードを入力" onPress={() => router.push('/join')} />
        <QuickAction
          testID="dashboard-quick-action-participating-productions"
          label="参加している公演・活動"
          onPress={() => router.push('/participating-productions')}
        />
      </View>

      <View style={styles.columns}>
        <View style={styles.mainColumn}>
          <UpcomingRehearsalsSection
            isLoading={dashboardQuery.isLoading}
            isError={dashboardQuery.isError}
            error={dashboardQuery.error}
            onRetry={() => dashboardQuery.refetch()}
            items={dashboardViewModel?.upcomingRehearsals ?? []}
          />

          <OrganizationsOverviewSection organizationsQuery={organizationsQuery} />

          <FollowedFeedSection items={dashboardViewModel?.followedOrganizationsFeed ?? []} />
        </View>

        <View style={styles.sideColumn}>
          <NotificationsSection items={dashboardViewModel?.notifications ?? []} />
        </View>
      </View>
    </WebLayout>
  );
}

function greetingForCurrentTime(): string {
  const hour = new Date().getHours();
  if (hour < 11) return 'おはようございます';
  if (hour < 18) return 'こんにちは';
  return 'こんばんは';
}

function QuickAction({
  testID,
  label,
  onPress,
  primary,
}: {
  testID: string;
  label: string;
  onPress: () => void;
  primary?: boolean;
}) {
  return (
    <TouchableOpacity testID={testID} onPress={onPress} style={[styles.quickAction, primary && styles.quickActionPrimary]}>
      <ThemedText type="smallBold" style={primary ? styles.quickActionTextPrimary : styles.quickActionText}>
        {label}
      </ThemedText>
    </TouchableOpacity>
  );
}

function SectionCard({ title, testID, children }: { title: string; testID?: string; children: React.ReactNode }) {
  return (
    <View style={styles.section} testID={testID}>
      <ThemedText type="subtitle" style={styles.sectionTitle}>
        {title}
      </ThemedText>
      {children}
    </View>
  );
}

/** Same data/navigation as home.tsx's PersonalOverviewSection (Phase 7.5) -
 * only the layout (a dashboard column card, not a mobile full-width
 * stack) differs. */
function UpcomingRehearsalsSection({
  isLoading,
  isError,
  error,
  onRetry,
  items,
}: {
  isLoading: boolean;
  isError: boolean;
  error: unknown;
  onRetry: () => void;
  items: UpcomingRehearsalViewModel[];
}) {
  const router = useRouter();

  if (isLoading) {
    return (
      <View style={styles.centered}>
        <ActivityIndicator testID="dashboard-rehearsals-loading" />
      </View>
    );
  }

  if (isError) {
    return (
      <View style={styles.centered}>
        <ThemedText testID="dashboard-rehearsals-error">{getErrorMessage(error)}</ThemedText>
        <TouchableOpacity onPress={onRetry} testID="dashboard-rehearsals-retry">
          <ThemedText type="link">再読み込み</ThemedText>
        </TouchableOpacity>
      </View>
    );
  }

  if (items.length === 0) {
    return null;
  }

  return (
    <SectionCard title="次の稽古・活動予定" testID="dashboard-upcoming-rehearsals-section">
      <View style={styles.list} testID="dashboard-upcoming-rehearsals-list">
        {items.map((rehearsal) => (
          <TouchableOpacity
            key={rehearsal.rehearsalId}
            testID={`dashboard-upcoming-rehearsal-row-${rehearsal.rehearsalId}`}
            style={styles.card}
            onPress={() => router.push(`/production/${rehearsal.productionId}/schedule/attendance/${rehearsal.rehearsalId}`)}
          >
            <View style={styles.titleRow}>
              {rehearsal.isUnanswered && <View style={styles.unansweredDot} testID={`dashboard-upcoming-rehearsal-unanswered-${rehearsal.rehearsalId}`} />}
              <ThemedText type="smallBold">{rehearsal.productionName}</ThemedText>
            </View>
            <ThemedText type="small">{rehearsal.title ?? '稽古'}</ThemedText>
            {rehearsal.dateDisplay && (
              <ThemedText type="small" themeColor="textSecondary">
                {rehearsal.dateDisplay}
                {rehearsal.timeDisplay ? ` ${rehearsal.timeDisplay}` : ''}
                {rehearsal.location ? ` ・ ${rehearsal.location}` : ''}
              </ThemedText>
            )}
          </TouchableOpacity>
        ))}
      </View>
    </SectionCard>
  );
}

/**
 * "参加中／管理中の団体" and "参加中／管理中の公演" (this Phase's own
 * requirement list) collapsed into one section, one row per Organization:
 * Role + 公開状態 identify what the Person can DO with it, and each
 * Organization's own Production names are listed inline (mirrors
 * organizations/index.tsx's own per-row useOrganizationProductions(org.id)
 * call - see that screen's docblock for why this stays client-side
 * filtering of the same two flat, already-fetched ['productions']/
 * ['projects'] Queries rather than N extra network requests).
 */
function OrganizationsOverviewSection({
  organizationsQuery,
}: {
  organizationsQuery: ReturnType<typeof useOrganizations>;
}) {
  const router = useRouter();

  return (
    <SectionCard title="団体" testID="dashboard-organizations-section">
      <View style={styles.sectionHeaderRow}>
        <TouchableOpacity testID="dashboard-organizations-view-all" onPress={() => router.push('/organizations')}>
          <ThemedText type="link">団体一覧を見る</ThemedText>
        </TouchableOpacity>
        <TouchableOpacity testID="dashboard-organizations-create" onPress={() => router.push('/organizations/create')}>
          <ThemedText type="link">＋ 団体を作る</ThemedText>
        </TouchableOpacity>
      </View>

      {organizationsQuery.isLoading && <ActivityIndicator testID="dashboard-organizations-loading" />}
      {organizationsQuery.isError && (
        <ThemedText testID="dashboard-organizations-error">{getErrorMessage(organizationsQuery.error)}</ThemedText>
      )}
      {!organizationsQuery.isLoading && !organizationsQuery.isError && (organizationsQuery.data?.length ?? 0) === 0 && (
        <ThemedText testID="dashboard-organizations-empty" themeColor="textSecondary">
          まだ団体がありません。「団体を作る」から最初の団体を作成してください。
        </ThemedText>
      )}

      {(organizationsQuery.data?.length ?? 0) > 0 && (
        <View style={styles.list} testID="dashboard-organizations-list">
          {organizationsQuery.data?.map((organization) => (
            <DashboardOrganizationCard key={organization.id} organization={organization} />
          ))}
        </View>
      )}
    </SectionCard>
  );
}

function DashboardOrganizationCard({ organization }: { organization: Organization }) {
  const router = useRouter();
  const productionsQuery = useOrganizationProductions(organization.id);
  const productions = productionsQuery.data ?? [];

  return (
    <TouchableOpacity
      testID={`dashboard-organization-row-${organization.id}`}
      style={styles.card}
      onPress={() => router.push(`/organizations/${organization.id}` as Href)}
    >
      <View style={styles.titleRow}>
        <ThemedText type="smallBold">{organization.name}</ThemedText>
        <RolePill role={organization.current_person_role} />
        <StatusPill published={!!organization.published_at} />
      </View>

      {productionsQuery.isLoading && <ActivityIndicator testID={`dashboard-organization-productions-loading-${organization.id}`} />}

      {!productionsQuery.isLoading && productions.length === 0 && (
        <ThemedText type="small" themeColor="textSecondary">
          公演・活動はまだありません
        </ThemedText>
      )}

      {productions.length > 0 && (
        <ThemedText type="small" themeColor="textSecondary" numberOfLines={1}>
          公演・活動 {productions.length}件：{productions.map((production) => production.name).join('、')}
        </ThemedText>
      )}
    </TouchableOpacity>
  );
}

function RolePill({ role }: { role: string }) {
  return (
    <View style={[styles.pill, styles.pillRole]}>
      <ThemedText type="small" style={styles.pillTextRole}>
        {role === 'OWNER' ? 'オーナー' : 'メンバー'}
      </ThemedText>
    </View>
  );
}

function StatusPill({ published }: { published: boolean }) {
  return (
    <View style={[styles.pill, published ? styles.pillPublished : styles.pillDraft]}>
      <ThemedText type="small" style={published ? styles.pillTextPublished : styles.pillTextDraft}>
        {published ? '公開中' : '下書き'}
      </ThemedText>
    </View>
  );
}

/** Same data/navigation as home.tsx's PersonalOverviewSection notifications
 * half (Phase 7.5). */
function NotificationsSection({ items }: { items: DashboardNotificationViewModel[] }) {
  const router = useRouter();

  if (items.length === 0) {
    return null;
  }

  return (
    <SectionCard title="お知らせ" testID="dashboard-notifications-section">
      <View style={styles.list} testID="dashboard-notifications-list">
        {items.map((notification) => (
          <TouchableOpacity
            key={notification.id}
            testID={`dashboard-notification-row-${notification.id}`}
            style={styles.card}
            onPress={() => router.push(`/production/${notification.productionId}/notifications`)}
          >
            <ThemedText type={notification.isRead ? 'default' : 'smallBold'}>{notification.title}</ThemedText>
            {notification.summary && (
              <ThemedText type="small" themeColor="textSecondary">
                {notification.summary}
              </ThemedText>
            )}
          </TouchableOpacity>
        ))}
      </View>
    </SectionCard>
  );
}

/** Same data/navigation as home.tsx's FollowedOrganizationsFeedSection
 * (docs/03-FollowAndHomeExperience.md). */
function FollowedFeedSection({ items }: { items: FollowedOrganizationFeedItemViewModel[] }) {
  const router = useRouter();

  if (items.length === 0) {
    return null;
  }

  return (
    <SectionCard title="フォロー中の新着" testID="dashboard-followed-feed-section">
      <View style={styles.list} testID="dashboard-followed-feed-list">
        {items.map((item) => (
          <TouchableOpacity
            key={item.productionId}
            testID={`dashboard-followed-feed-row-${item.productionId}`}
            style={styles.card}
            onPress={() => {
              if (item.organizationSlug && item.productionSlug) {
                router.push(`/${item.organizationSlug}/${item.productionSlug}` as Href);
              }
            }}
          >
            <ThemedText type="smallBold">{item.organizationName}</ThemedText>
            <ThemedText type="small">新しい公演が公開されました</ThemedText>
            <ThemedText type="small" themeColor="textSecondary">
              {item.productionName}
              {item.dateDisplay ? ` ・ ${item.dateDisplay}` : ''}
            </ThemedText>
          </TouchableOpacity>
        ))}
      </View>
    </SectionCard>
  );
}

const styles = StyleSheet.create({
  headerRow: { flexDirection: 'row', alignItems: 'flex-start', justifyContent: 'space-between', marginBottom: Spacing.three },
  quickActions: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two, marginBottom: Spacing.four },
  quickAction: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.three,
    backgroundColor: '#fff',
  },
  quickActionPrimary: { backgroundColor: BrandColors.warmAmber, borderColor: BrandColors.warmAmber },
  quickActionText: { color: '#2A2320' },
  quickActionTextPrimary: { color: '#fff' },
  columns: { flexDirection: 'row', gap: Spacing.four, alignItems: 'flex-start' },
  mainColumn: { flex: 2, minWidth: 320, gap: Spacing.four },
  sideColumn: { flex: 1, minWidth: 260, gap: Spacing.four },
  section: { gap: Spacing.two },
  sectionTitle: { marginBottom: Spacing.half },
  sectionHeaderRow: { flexDirection: 'row', justifyContent: 'space-between', marginBottom: Spacing.one },
  centered: { alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  list: { gap: Spacing.two },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.half,
    backgroundColor: '#fff',
  },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.one, flexWrap: 'wrap' },
  unansweredDot: { width: 8, height: 8, borderRadius: 4, backgroundColor: '#3c87f7' },
  pill: { paddingHorizontal: Spacing.two, paddingVertical: Spacing.half, borderRadius: Radius.medium },
  pillRole: { backgroundColor: '#eef0fb' },
  pillTextRole: { color: '#3a4baf', fontWeight: '600' },
  pillPublished: { backgroundColor: '#e3f3e8' },
  pillDraft: { backgroundColor: '#f7e4de' },
  pillTextPublished: { color: '#2f7a4a', fontWeight: '600' },
  pillTextDraft: { color: '#a6483a', fontWeight: '600' },
});
