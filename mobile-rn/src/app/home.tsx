import { useRouter, type Href } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Alert, RefreshControl, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';

import { AppShell } from '@/components/app-shell';
import { OrganizationTile } from '@/components/organization-tile';
import { ProductionCard } from '@/components/production-card';
import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Radius, Spacing } from '@/constants/theme';
import {
  buildDashboardViewModel,
  type DashboardNotificationViewModel,
  type FollowedOrganizationFeedItemViewModel,
  type UpcomingRehearsalViewModel,
} from '@/features/dashboard/viewModel';
import { useMyDashboard } from '@/features/dashboard/useDashboard';
import { useLogout } from '@/features/mypage/useLogout';
import { useOrganizationContext } from '@/features/organization/OrganizationContext';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { useOrganizationProductions } from '@/features/production/useProductions';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import { getErrorMessage } from '@/utils/errorMessage';

type NavEntry = {
  key: string;
  label: string;
  route: '/discover-organizations' | '/discover-productions' | '/viewing-history' | '/participating-productions' | '/profile' | '/favorites';
};

/**
 * BusinessFlowUXClarifications.md §02: a newly-registered general user
 * normally has NO Organization/Production membership, so Home is
 * Person-first, not an "業務管理システムのダッシュボード" - the 5 entry
 * points below (§02.1) are always shown, regardless of affiliation.
 * Organization/Production MANAGEMENT features (the Organization switcher
 * + Production list this screen used to lead with) are shown only when
 * the Person actually has a real Membership (§02.6) - never blocking or
 * even visually competing with the unaffiliated state.
 *
 * "次の稽古"/"お知らせ" (Personal Overview, via GET /me/dashboard) is
 * kept from the pre-existing implementation unchanged - it is itself
 * Organization/Production-非依存 (cross-Production by definition) and
 * degrades to a natural empty state for an unaffiliated user, matching
 * §02.4/§02.5's "ゼロ件は正常、エラーではない" principle.
 */
export default function HomeScreen() {
  const router = useRouter();
  const { currentOrganizationId, selectOrganization } = useOrganizationContext();
  const [pickerOpen, setPickerOpen] = useState(false);

  const dashboardQuery = useMyDashboard();
  const dashboardViewModel = dashboardQuery.data ? buildDashboardViewModel(dashboardQuery.data) : null;

  const {
    data: organizations,
    isLoading: organizationsLoading,
    isError: organizationsError,
    error: organizationsErrorObj,
  } = useOrganizations();

  // A lone Organization membership is auto-selected, skipping the
  // selection screen entirely.
  useEffect(() => {
    if (organizations && organizations.length === 1 && currentOrganizationId === null) {
      selectOrganization(organizations[0].id);
    }
  }, [organizations, currentOrganizationId, selectOrganization]);

  const currentOrganization = organizations?.find((organization) => organization.id === currentOrganizationId) ?? null;

  const showPicker = pickerOpen || (!!organizations && organizations.length > 1 && currentOrganizationId === null);

  const productionsQuery = useOrganizationProductions(showPicker ? null : currentOrganizationId);

  function handleSelectOrganization(id: string) {
    selectOrganization(id);
    setPickerOpen(false);
  }

  const hasOrganizations = !organizationsLoading && !organizationsError && !!organizations && organizations.length > 0;

  return (
    <AppShell>
      <ScrollView
        refreshControl={
          <RefreshControl
            refreshing={dashboardQuery.isFetching || productionsQuery.isFetching}
            onRefresh={() => {
              dashboardQuery.refetch();
              productionsQuery.refetch();
            }}
          />
        }
      >
        <GreetingHeader />

        <PrimaryNavGrid />

        <PersonalOverviewSection
          isLoading={dashboardQuery.isLoading}
          isError={dashboardQuery.isError}
          error={dashboardQuery.error}
          onRetry={() => dashboardQuery.refetch()}
          upcomingRehearsals={dashboardViewModel?.upcomingRehearsals ?? []}
          notifications={dashboardViewModel?.notifications ?? []}
        />

        <FollowedOrganizationsFeedSection items={dashboardViewModel?.followedOrganizationsFeed ?? []} />

        {/* §02.6: Organization/Production管理機能は、実際にMembershipを
            持つ利用者にのみ表示する - 未所属の利用者には一切表示しない
            (ローディング/エラー表示も含め、このセクション自体を出さない)。 */}
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

        {!organizationsLoading && !organizationsError && (
          <ThemedView style={styles.centered}>
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
          <ThemedView style={styles.managementSection}>
            <ThemedText type="subtitle" style={styles.sectionTitle}>
              団体の管理
            </ThemedText>

            {!showPicker && currentOrganization && (
              <TouchableOpacity style={styles.organizationBar} onPress={() => setPickerOpen(true)} testID="organization-switcher">
                <ThemedText type="default">{currentOrganization.name} ▼</ThemedText>
              </TouchableOpacity>
            )}

            {showPicker && (
              <ThemedView testID="organization-picker" style={styles.list}>
                {organizations?.map((organization) => (
                  <OrganizationTile
                    key={organization.id}
                    organization={organization}
                    onPress={() => handleSelectOrganization(organization.id)}
                  />
                ))}
              </ThemedView>
            )}

            {!showPicker && currentOrganizationId && (
              <>
                {productionsQuery.isLoading && (
                  <ThemedView style={styles.centered}>
                    <ActivityIndicator />
                  </ThemedView>
                )}

                {productionsQuery.isError && (
                  <ThemedView style={styles.centered}>
                    <ThemedText testID="productions-error">{getErrorMessage(productionsQuery.error)}</ThemedText>
                  </ThemedView>
                )}

                {!productionsQuery.isLoading && !productionsQuery.isError && (productionsQuery.data?.length ?? 0) === 0 && (
                  <ThemedText testID="productions-empty" style={styles.centered}>
                    この団体には公演・活動がありません。
                  </ThemedText>
                )}

                {!productionsQuery.isLoading && !productionsQuery.isError && (productionsQuery.data?.length ?? 0) > 0 && (
                  <ThemedView testID="production-list" style={styles.list}>
                    {productionsQuery.data?.map((production) => (
                      <ProductionCard
                        key={production.id}
                        production={production}
                        onPress={() => router.push(`/production/${production.id}/schedule`)}
                      />
                    ))}
                  </ThemedView>
                )}
              </>
            )}
          </ThemedView>
        )}

        <HomeLogoutButton />
      </ScrollView>
    </AppShell>
  );
}

/**
 * StageArt Google/ログアウト優先修正: an explicit logout entry point on
 * Home itself, in addition to the existing one on Profile (§04's earlier
 * "no logout in Home's header" decision only ruled out the header - this
 * is a plain in-content button, and today's instruction is explicit
 * about wanting it reachable from Home directly). Reuses the exact same
 * useLogout() sequence (Auth state + SecureStore + Query cache clear +
 * navigate to /login) and the same Alert.alert confirmation pattern as
 * MyPageContent.tsx's logout button, for consistency.
 */
function HomeLogoutButton() {
  const logout = useLogout();
  const [loggingOut, setLoggingOut] = useState(false);

  const handleLogout = () => {
    Alert.alert('ログアウト', 'ログアウトしますか？', [
      { text: 'キャンセル', style: 'cancel' },
      {
        text: 'ログアウト',
        style: 'destructive',
        onPress: async () => {
          setLoggingOut(true);
          await logout();
        },
      },
    ]);
  };

  return (
    <TouchableOpacity
      onPress={handleLogout}
      disabled={loggingOut}
      testID="home-logout-button"
      accessibilityRole="button"
      accessibilityLabel="ログアウト"
      style={styles.logoutButton}
    >
      {loggingOut ? <ActivityIndicator testID="home-logout-loading" /> : <ThemedText type="linkPrimary">ログアウト</ThemedText>}
    </TouchableOpacity>
  );
}

/**
 * StageArt Authentication Phase 6: "おはようございます、[姓]さん" - lets
 * the user immediately confirm "今ログインしているアカウントが本当に自分
 * なのか" from the very first screen (this Phase's explicit purpose).
 * Reads GET /me's family_name only (not given_name - matches the
 * Japanese convention of addressing someone by surname, and the
 * Phase's own example text). Home is only ever reached once set-name.tsx
 * has been completed (see index.tsx's boot gate and login.tsx's
 * post-login routing), so family_name should always be present here in
 * practice - the loading/missing-data states below are defensive, not
 * expected to be visibly reached.
 */
function GreetingHeader() {
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
 * §02.1's 5 required entry points. "参加している公演・活動"/"プロフィール"
 * always apply; "団体を探す"/"公演・活動を探す"/"観劇履歴" currently land
 * on a "準備中" screen (no Backend discovery/history API exists yet -
 * see the gap-analysis report and the user's own decision to build the
 * navigation now, real data later). The internal Domain name "Project"
 * never appears here (§02.6/§03).
 */
function PrimaryNavGrid() {
  const router = useRouter();

  const entries: NavEntry[] = [
    { key: 'discover-organizations', label: '団体を探す', route: '/discover-organizations' },
    { key: 'discover-productions', label: '公演・活動を探す', route: '/discover-productions' },
    { key: 'participating-productions', label: '参加している\n公演・活動', route: '/participating-productions' },
    { key: 'viewing-history', label: '観劇履歴', route: '/viewing-history' },
    { key: 'favorites', label: 'お気に入り', route: '/favorites' },
    { key: 'profile', label: 'プロフィール', route: '/profile' },
  ];

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

  // StageArt Web First Phase 1 (docs/04-HomeRoleBasedMenu.md §02/§10,
  // docs/04-DomainModel/Follow.md "Home Information Priority"): "次回
  // 稽古なし" / "お知らせなし" are explicitly prohibited placeholder text
  // - a section (including its own header) only ever renders when real
  // data exists for it. No information at all means this whole section
  // renders nothing, not an empty-state message.
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
 * docs/03-FollowAndHomeExperience.md's "3. Follow中の新着": a Person's
 * own next activity comes first (PersonalOverviewSection above), this
 * comes next, 観劇履歴 after. Renders nothing at all when the feed is
 * empty (no "フォローなし" placeholder) - same "存在する情報だけを表示"
 * principle as PersonalOverviewSection's own empty-state handling.
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
                router.push(`/o/${item.organizationSlug}/${item.productionSlug}` as Href);
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
  organizationBar: {
    paddingHorizontal: Spacing.four,
    paddingVertical: Spacing.two,
  },
  centered: { alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  list: { paddingHorizontal: Spacing.three, gap: Spacing.two },
  section: { paddingBottom: Spacing.two },
  managementSection: { paddingBottom: Spacing.four },
  sectionTitle: { paddingHorizontal: Spacing.four, paddingTop: Spacing.three, paddingBottom: Spacing.one },
  sectionBody: { paddingHorizontal: Spacing.four, paddingBottom: Spacing.two },
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: Radius.medium,
    padding: Spacing.three,
    gap: Spacing.half,
  },
  titleRow: { flexDirection: 'row', alignItems: 'center', gap: Spacing.one },
  unansweredDot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: '#3c87f7',
  },
  logoutButton: {
    alignItems: 'center',
    paddingVertical: Spacing.three,
  },
});
