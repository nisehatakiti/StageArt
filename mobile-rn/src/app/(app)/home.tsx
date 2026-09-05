import { useRouter, type Href } from 'expo-router';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, TouchableOpacity, View } from 'react-native';

import { ProductionCard } from '@/components/production-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { TwoColumnLayout } from '@/components/chrome/TwoColumnLayout';
import { BrandColors, Radius, Spacing } from '@/constants/theme';
import {
  buildDashboardViewModel,
  type DashboardNotificationViewModel,
  type FollowedOrganizationFeedItemViewModel,
  type UpcomingRehearsalViewModel,
} from '@/features/dashboard/viewModel';
import { useMyDashboard } from '@/features/dashboard/useDashboard';
import { useMyFavorites } from '@/features/favorite/useFavorite';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { useOrganizationProductions } from '@/features/production/useProductions';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import type { Organization } from '@/types/api';
import { getErrorMessage } from '@/utils/errorMessage';

type NavEntry = {
  key: string;
  label: string;
  route: '/favorites';
};

/**
 * StageArt Blueprint再構成 Phase 1c: the single canonical Home, replacing
 * the separate home.tsx (mobile Person-first) and dashboard.tsx (Web
 * "management overview") screens - StageArt does not have an
 * admin-only Home and a general-user Home; every authenticated Person
 * lands here regardless of Organization/Production affiliation, and
 * what's shown grows with their actual relationships (§02.1/§02.6),
 * never a separate screen.
 *
 * Section provenance (kept, not rebuilt - see the Phase 1 plan's own
 * "don't over-engineer a redesign" note):
 * - GreetingHeader / PrimaryNavGrid / PersonalOverviewSection (次の稽古+
 *   お知らせ, combined) / FollowedOrganizationsFeedSection: home.tsx,
 *   unchanged logic.
 * - The email-unverified notice (dashboard.tsx) is folded into
 *   GreetingHeader, so that information survives dashboard.tsx's
 *   retirement.
 * - OrganizationsSection: dashboard.tsx's own card-per-Organization
 *   design (Role pill + published pill + inline Production count/names)
 *   replaces home.tsx's single-"current Organization" switcher/picker
 *   entirely (§4 - no Organization Switcher, every Organization the
 *   Person belongs to renders as its own card, unconditionally).
 * - TwoColumnLayout (§6) wraps Organizations (main) + Followed feed
 *   (side) on wide viewports; PersonalOverviewSection stays full-width
 *   above it (it is itself Organization/Production-non-specific, so
 *   splitting it across columns would not have been "reusing existing
 *   UI", just rearranging it).
 *
 * No Logout button here (§7) - Logout lives in the common menu
 * (WebSidebarNav/NativeDrawerMenu, both reachable from AppChrome on
 * every authenticated screen) exactly once.
 *
 * StageArt Web検証 STEP4 (2026-09-05): partial alignment with
 * docs/12-FunctionalStructure.md §15.1 Initial Screen / Home (Confirmed
 * business specification, origin/main commit c1aa328) - referenced
 * read-only via `git show origin/main:docs/12-FunctionalStructure.md`,
 * since that commit is not an ancestor of this branch (last merged
 * origin/main on 2026-08-28, five days before §15.1 was confirmed on
 * 2026-09-02 - not a deliberate decision to diverge from it, the file
 * was simply never present here). §15.1's basic menu (団体を探す/公演を
 * 探す/プロフィール/経費精算/アカウント管理/ログアウト) is already reachable
 * from WebSidebarNav/NativeDrawerMenu (useNavMenu.ts) - three of
 * PrimaryNavGrid's six tiles duplicated that same menu inside Home's own
 * body, so they're removed here: 団体を探す/公演・活動を探す/プロフィール.
 * "参加している公演・活動" is also removed - not one of §15.1's Home
 * elements, and ProfileContent.tsx already has its own link to the same
 * destination ("profile-productions-view-all"), so nothing becomes
 * unreachable. "参加コードを入力" (QuickCreateRow) is removed the same
 * way - ProfileContent.tsx already has "profile-join-link" to the same
 * `/join` destination.
 *
 * Deliberately NOT touched this Phase (see this Phase's own report for
 * the reasoning): "＋団体を作る"/the Organization list section (a
 * brand-new Person with zero Organizations has no other confirmed path
 * to `/organizations` today - useNavMenu.ts's "団体情報" only appears
 * once management permission already exists), and 全体通知 (§15.1 names
 * it, but no Domain/Application/REST/Frontend implementation exists
 * anywhere to wire up - not invented here).
 *
 * StageArt Home仕様追加 (2026-09-05): 観劇履歴/お気に入り are no longer
 * fixed tiles - PrimaryNavGrid now shows each only when the signed-in
 * Person actually has at least one, per this Phase's own "Home shows
 * only what's currently relevant to this Person" rule. お気に入り uses
 * useMyFavorites() (an existing, already-working Backend Favorite
 * Domain/API - see favorites.tsx) directly - no new Domain/API. 観劇履歴
 * has no such data source: no audience/viewing-history Domain,
 * Application, or REST endpoint exists anywhere in this codebase (see
 * viewing-history.tsx's own docblock - it is still a plain "準備中"
 * placeholder screen, not backed by any real data). There is therefore
 * no way to ever confirm "1件以上ある" for it, and per this Phase's own
 * §13 safety rule (never show data whose existence isn't confirmed),
 * 観劇履歴 is not included in PrimaryNavGrid at all right now - it is
 * not deleted (viewing-history.tsx itself, and its route, are
 * untouched), just unreachable from Home until a real Backend data
 * source exists to gate it on. Flagged as a genuine, unresolved Backend
 * gap in this Phase's own report, not decided unilaterally here.
 */
export default function HomeScreen() {
  const router = useRouter();
  const currentPersonQuery = useCurrentPerson();
  const dashboardQuery = useMyDashboard();
  const dashboardViewModel = dashboardQuery.data ? buildDashboardViewModel(dashboardQuery.data) : null;

  const { data: organizations, isLoading: organizationsLoading, isError: organizationsError, error: organizationsErrorObj } = useOrganizations();

  const hasOrganizations = !organizationsLoading && !organizationsError && !!organizations && organizations.length > 0;

  // Loading and error both resolve to "not confirmed" - never shown as
  // if it were confirmed present (this Phase's own §13 safety rule).
  const favoritesQuery = useMyFavorites();
  const hasFavorites = !favoritesQuery.isLoading && !favoritesQuery.isError && (favoritesQuery.data?.length ?? 0) > 0;

  return (
    <ScrollView
      refreshControl={
        <RefreshControl
          refreshing={dashboardQuery.isFetching}
          onRefresh={() => {
            dashboardQuery.refetch();
          }}
        />
      }
    >
      <GreetingHeader />

      <PrimaryNavGrid hasFavorites={hasFavorites} />

      <QuickCreateRow />

      <PersonalOverviewSection
        isLoading={dashboardQuery.isLoading}
        isError={dashboardQuery.isError}
        error={dashboardQuery.error}
        onRetry={() => dashboardQuery.refetch()}
        upcomingRehearsals={dashboardViewModel?.upcomingRehearsals ?? []}
        notifications={dashboardViewModel?.notifications ?? []}
      />

      <View style={styles.section}>
        <TwoColumnLayout
          main={
            <>
              <ThemedText type="subtitle" style={styles.sectionTitle}>
                団体
              </ThemedText>

              {organizationsLoading && (
                <ThemedView style={styles.centered}>
                  <ActivityIndicator testID="organizations-loading" />
                </ThemedView>
              )}

              {organizationsError && (
                <ThemedView style={styles.centered}>
                  <ThemedText testID="organizations-error">{getErrorMessage(organizationsErrorObj)}</ThemedText>
                </ThemedView>
              )}

              {!organizationsLoading && !organizationsError && !hasOrganizations && (
                <ThemedView style={styles.centered}>
                  <ThemedText type="small" themeColor="textSecondary" testID="organizations-empty">
                    まだ団体がありません。
                  </ThemedText>
                  <TouchableOpacity
                    testID="home-create-organization"
                    onPress={() => router.push('/organizations/create')}
                    accessibilityRole="button"
                    accessibilityLabel="団体を作る"
                  >
                    <ThemedText type="link">＋ 団体を作る</ThemedText>
                  </TouchableOpacity>
                </ThemedView>
              )}

              {hasOrganizations && (
                <ThemedView style={styles.list} testID="home-organizations-list">
                  {organizations.map((organization) => (
                    <HomeOrganizationCard key={organization.id} organization={organization} />
                  ))}
                </ThemedView>
              )}
            </>
          }
          // フォロー中の新着はPerson単位(Organization/Production非依存)の
          // 情報のため、所属団体の有無に関係なく常に試みる - 中身が空なら
          // FollowedOrganizationsFeedSection自身が何も描画しない。
          side={<FollowedOrganizationsFeedSection items={dashboardViewModel?.followedOrganizationsFeed ?? []} />}
        />
      </View>
    </ScrollView>
  );
}

/**
 * StageArt Authentication Phase 6 (greeting) + dashboard.tsx (email-
 * unverified notice, folded in here per this file's own docblock):
 * "おはようございます、[姓]さん" lets the user immediately confirm "今
 * ログインしているアカウントが本当に自分なのか" from the very first
 * screen. Reads GET /me's family_name only (Japanese surname-address
 * convention). Home is only ever reached once set-name.tsx has been
 * completed, so family_name should always be present in practice.
 */
function GreetingHeader() {
  const router = useRouter();
  const currentPersonQuery = useCurrentPerson();
  const familyName = currentPersonQuery.data?.family_name;

  if (!familyName) {
    return null;
  }

  return (
    <ThemedView style={styles.greeting}>
      <ThemedText type="subtitle" testID="home-greeting">
        {greetingForCurrentTime()}、{familyName}さん
      </ThemedText>
      {currentPersonQuery.data && !currentPersonQuery.data.email_verified && (
        <ThemedText type="small" themeColor="textSecondary" testID="home-email-unverified">
          メールアドレスが未確認です。
          <ThemedText type="link" onPress={() => router.push('/profile')}>
            プロフィールで確認する
          </ThemedText>
        </ThemedText>
      )}
    </ThemedView>
  );
}

function greetingForCurrentTime(): string {
  const hour = new Date().getHours();
  if (hour < 11) return 'おはようございます';
  if (hour < 18) return 'こんにちは';
  return 'こんばんは';
}

/**
 * StageArt Web検証 STEP4: reduced from six tiles down (団体を探す/公演・
 * 活動を探す/参加している公演・活動/プロフィール removed - already
 * reachable via the confirmed §15.1 Menu or ProfileContent.tsx).
 *
 * StageArt Home仕様追加 (2026-09-05): the only remaining tile
 * (お気に入り) is now itself conditional on `hasFavorites` - see this
 * file's own top docblock for why 観劇履歴 is not included at all (no
 * Backend data source exists to confirm it). Returns null entirely
 * rather than an empty container when there is nothing to show, the
 * same "存在する情報だけを表示する" pattern PersonalOverviewSection/
 * FollowedOrganizationsFeedSection already use.
 */
function PrimaryNavGrid({ hasFavorites }: { hasFavorites: boolean }) {
  const router = useRouter();

  const entries: NavEntry[] = hasFavorites ? [{ key: 'favorites', label: 'お気に入り', route: '/favorites' }] : [];

  if (entries.length === 0) {
    return null;
  }

  return (
    <ThemedView style={styles.navGrid} testID="home-primary-nav">
      {entries.map((entry) => (
        <TouchableOpacity
          key={entry.key}
          testID={`home-nav-${entry.key}`}
          style={styles.navTile}
          onPress={() => router.push(entry.route)}
          accessibilityRole="button"
          accessibilityLabel={entry.label.replace('\n', '')}
        >
          <ThemedText type="smallBold" style={styles.navTileText}>
            {entry.label}
          </ThemedText>
        </TouchableOpacity>
      ))}
    </ThemedView>
  );
}

/**
 * StageArt Web検証 STEP4: "参加コードを入力" removed here -
 * ProfileContent.tsx already has the same destination
 * ("profile-join-link" -> `/join`), so nothing becomes unreachable.
 * "＋団体を作る" is kept: a brand-new Person with zero Organizations has
 * no other confirmed path to Organization creation today (see this
 * file's own top docblock) - removing this would be a real functional
 * regression, not a cosmetic Home cleanup, so it stays until that
 * navigation gap is resolved elsewhere.
 */
function QuickCreateRow() {
  const router = useRouter();

  return (
    <View style={styles.quickActions} testID="home-quick-actions">
      <TouchableOpacity
        testID="home-quick-action-create-organization"
        style={[styles.quickAction, styles.quickActionPrimary]}
        onPress={() => router.push('/organizations/create')}
      >
        <ThemedText type="smallBold" style={styles.quickActionTextPrimary}>
          ＋ 団体を作る
        </ThemedText>
      </TouchableOpacity>
    </View>
  );
}

function PersonalOverviewSection({
  isLoading,
  isError,
  error,
  onRetry,
  upcomingRehearsals,
  notifications,
}: {
  isLoading: boolean;
  isError: boolean;
  error: unknown;
  onRetry: () => void;
  upcomingRehearsals: UpcomingRehearsalViewModel[];
  notifications: DashboardNotificationViewModel[];
}) {
  const router = useRouter();

  if (isLoading) {
    return (
      <ThemedView style={styles.centered}>
        <ActivityIndicator testID="dashboard-loading" />
      </ThemedView>
    );
  }

  if (isError) {
    return (
      <ThemedView style={styles.centered}>
        <ThemedText testID="dashboard-error">{getErrorMessage(error)}</ThemedText>
        <TouchableOpacity onPress={onRetry} testID="dashboard-retry" accessibilityRole="button" accessibilityLabel="再読み込み">
          <ThemedText type="link">再読み込み</ThemedText>
        </TouchableOpacity>
      </ThemedView>
    );
  }

  const hasRehearsals = upcomingRehearsals.length > 0;
  const hasNotifications = notifications.length > 0;

  // "次回稽古なし" / "お知らせなし" are explicitly prohibited placeholder
  // text - a section (including its own header) only ever renders when
  // real data exists for it.
  if (!hasRehearsals && !hasNotifications) {
    return null;
  }

  return (
    <ThemedView style={styles.section}>
      {hasRehearsals && (
        <>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            次の稽古
          </ThemedText>
          <ThemedView testID="upcoming-rehearsals-list" style={styles.list}>
            {upcomingRehearsals.map((rehearsal) => (
              <TouchableOpacity
                key={rehearsal.rehearsalId}
                testID={`upcoming-rehearsal-row-${rehearsal.rehearsalId}`}
                style={styles.card}
                onPress={() => router.push(`/production/${rehearsal.productionId}/schedule/attendance/${rehearsal.rehearsalId}`)}
              >
                <ThemedView style={styles.titleRow}>
                  {rehearsal.isUnanswered && <ThemedView style={styles.unansweredDot} testID="upcoming-rehearsal-unanswered-dot" />}
                  <ThemedText type="smallBold">{rehearsal.productionName}</ThemedText>
                </ThemedView>
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
          </ThemedView>
        </>
      )}

      {hasNotifications && (
        <>
          <ThemedText type="subtitle" style={styles.sectionTitle}>
            お知らせ
          </ThemedText>
          <ThemedView testID="home-notifications-list" style={styles.list}>
            {notifications.map((notification) => (
              <TouchableOpacity
                key={notification.id}
                testID={`home-notification-row-${notification.id}`}
                style={styles.card}
                onPress={() => router.push(`/production/${notification.productionId}/notifications`)}
              >
                <ThemedText type={notification.isRead ? 'default' : 'smallBold'}>{notification.title}</ThemedText>
                {notification.summary && <ThemedText type="small">{notification.summary}</ThemedText>}
              </TouchableOpacity>
            ))}
          </ThemedView>
        </>
      )}
    </ThemedView>
  );
}

/**
 * §4: replaces the previous single-"current Organization" switcher -
 * every Organization the Person belongs to renders as its own card,
 * unconditionally (no picker, no selection state). Role/published-state
 * pills are dashboard.tsx's own, proven design (DashboardOrganizationCard),
 * carried over unchanged; each of that Organization's own Productions
 * renders as a real, individually-tappable ProductionCard beneath it
 * (home.tsx's own previous "production-list" capability, only reachable
 * before for the single "current" Organization - now available per-card,
 * for every Organization, matching §4's "必要なProduction情報も...表示
 * する").
 *
 * The Owner-only invite-management link (home.tsx's old
 * `organization-invite-link`, previously shown only for the single
 * "current" Organization) now appears per-card, for every Organization
 * this Person owns - a strict improvement (multi-Organization owners can
 * now reach every Organization's invite management without switching),
 * not a new permission concept (still exactly `current_person_role ===
 * 'OWNER'`, the same field/check as before).
 */
function HomeOrganizationCard({ organization }: { organization: Organization }) {
  const router = useRouter();
  const productionsQuery = useOrganizationProductions(organization.id);
  const productions = productionsQuery.data ?? [];
  const isOwner = organization.current_person_role === 'OWNER';

  return (
    <View testID={`home-organization-row-${organization.id}`} style={styles.card}>
      <TouchableOpacity
        testID={`home-organization-link-${organization.id}`}
        onPress={() => router.push(`/organizations/${organization.id}` as Href)}
      >
        <View style={styles.titleRow}>
          <ThemedText type="smallBold">{organization.name}</ThemedText>
          <RolePill role={organization.current_person_role} />
          <StatusPill published={!!organization.published_at} />
        </View>
      </TouchableOpacity>

      {productionsQuery.isLoading && <ActivityIndicator testID={`home-organization-productions-loading-${organization.id}`} />}

      {productionsQuery.isError && (
        <ThemedText testID="productions-error">{getErrorMessage(productionsQuery.error)}</ThemedText>
      )}

      {!productionsQuery.isLoading && !productionsQuery.isError && productions.length === 0 && (
        <ThemedText testID="productions-empty" type="small" themeColor="textSecondary">
          公演・活動はまだありません
        </ThemedText>
      )}

      {productions.length > 0 && (
        <View testID={`home-organization-productions-${organization.id}`}>
          {productions.map((production) => (
            <ProductionCard key={production.id} production={production} onPress={() => router.push(`/production/${production.id}/schedule`)} />
          ))}
        </View>
      )}

      {isOwner && (
        <TouchableOpacity
          style={styles.inviteLink}
          onPress={() => router.push(`/organizations/${organization.id}/invite` as Href)}
          testID={`home-organization-invite-link-${organization.id}`}
        >
          <ThemedText type="link">団体への参加・参加申請の管理</ThemedText>
        </TouchableOpacity>
      )}
    </View>
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

/**
 * docs/03-FollowAndHomeExperience.md's "3. Follow中の新着". Renders
 * nothing at all when the feed is empty (no "フォローなし" placeholder) -
 * same "存在する情報だけを表示" principle used throughout this screen.
 */
function FollowedOrganizationsFeedSection({ items }: { items: FollowedOrganizationFeedItemViewModel[] }) {
  const router = useRouter();

  if (items.length === 0) {
    return null;
  }

  return (
    <ThemedView style={styles.section} testID="followed-organizations-feed-section">
      <ThemedText type="subtitle" style={styles.sectionTitle}>
        フォロー中の新着
      </ThemedText>
      <ThemedView testID="followed-organizations-feed-list" style={styles.list}>
        {items.map((item) => (
          <TouchableOpacity
            key={item.productionId}
            testID={`followed-organization-feed-row-${item.productionId}`}
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
      </ThemedView>
    </ThemedView>
  );
}

const styles = StyleSheet.create({
  greeting: {
    paddingHorizontal: Spacing.four,
    paddingTop: Spacing.three,
    gap: Spacing.half,
  },
  navGrid: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    paddingHorizontal: Spacing.three,
    paddingTop: Spacing.two,
    paddingBottom: Spacing.one,
    gap: Spacing.two,
  },
  navTile: {
    flexBasis: '31%',
    flexGrow: 1,
    minHeight: 72,
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: Spacing.two,
    paddingHorizontal: Spacing.one,
    borderRadius: Radius.medium,
    backgroundColor: '#F7E4DE',
  },
  navTileText: { textAlign: 'center' },
  quickActions: { flexDirection: 'row', flexWrap: 'wrap', gap: Spacing.two, paddingHorizontal: Spacing.three, paddingBottom: Spacing.two },
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
  inviteLink: {
    paddingTop: Spacing.two,
  },
  centered: { alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  list: { gap: Spacing.two },
  section: { paddingHorizontal: Spacing.three, paddingBottom: Spacing.two },
  sectionTitle: { paddingTop: Spacing.three, paddingBottom: Spacing.one },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.half,
  },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.one, flexWrap: 'wrap' },
  unansweredDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#3c87f7',
  },
  pill: { paddingHorizontal: Spacing.two, paddingVertical: Spacing.half, borderRadius: Radius.medium },
  pillRole: { backgroundColor: '#eef0fb' },
  pillTextRole: { color: '#3a4baf', fontWeight: '600' },
  pillPublished: { backgroundColor: '#e3f3e8' },
  pillDraft: { backgroundColor: '#f7e4de' },
  pillTextPublished: { color: '#2f7a4a', fontWeight: '600' },
  pillTextDraft: { color: '#a6483a', fontWeight: '600' },
});
