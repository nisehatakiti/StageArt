import { useRouter, type Href } from 'expo-router';
import { useAuth } from '@/auth/AuthContext';
import { ActivityIndicator, RefreshControl, ScrollView, StyleSheet, TouchableOpacity } from 'react-native';
import { useMemo } from 'react';

import { ThemedText } from '@/components/themed-text';
import { ThemedView } from '@/components/themed-view';
import { Radius, Spacing } from '@/constants/theme';
import {
  buildDashboardViewModel,
  type FollowedOrganizationFeedItemViewModel,
  type UpcomingRehearsalViewModel,
} from '@/features/dashboard/viewModel';
import { useMyDashboard } from '@/features/dashboard/useDashboard';
import { useMyFavorites } from '@/features/favorite/useFavorite';
import { useOrganizations } from '@/features/organization/useOrganizations';
import { fetchProjects } from '@/features/organization/api';
import { fetchProductions } from '@/features/production/api';
import { approveMembershipRequest, fetchPendingMembershipRequests, rejectMembershipRequest } from '@/features/membership/api';
import { useCurrentPerson } from '@/features/person/useCurrentPerson';
import { useMutation, useQueries, useQuery, useQueryClient } from '@tanstack/react-query';
import { useMarkMyNotificationRead, useMyNotifications } from '@/features/notifications/useNotifications';
import { getErrorMessage } from '@/utils/errorMessage';
import { phaseForRehearsalStatus } from '@/features/attendance/phase';
import { responseOptionsForPhase, statusLabel } from '@/features/attendance/viewModel';
import { useRehearsal } from '@/features/attendance/useRehearsals';
import { useRehearsalAttendances, useRespondRehearsalAttendance } from '@/features/attendance/useRehearsalAttendance';
import type { MyNotification } from '@/types/api';

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
  const { apiClient, status } = useAuth();
  const currentPersonQuery = useCurrentPerson();
  const dashboardQuery = useMyDashboard();
  const dashboardViewModel = dashboardQuery.data ? buildDashboardViewModel(dashboardQuery.data) : null;
  const notificationsQuery = useMyNotifications();
  const unreadNotifications = (notificationsQuery.data ?? []).filter((notification) => !notification.is_read);

  // Loading and error both resolve to "not confirmed" - never shown as
  // if it were confirmed present (this Phase's own §13 safety rule).
  const favoritesQuery = useMyFavorites();
  const organizationsQuery = useOrganizations();
  const productionsQuery = useQuery({
    queryKey: ['productions'],
    queryFn: () => fetchProductions(apiClient),
    enabled: status === 'authenticated',
  });
  const projectsQuery = useQuery({
    queryKey: ['projects'],
    queryFn: () => fetchProjects(apiClient),
    enabled: status === 'authenticated',
  });
  const productionOrganizationNames = useMemo(() => {
    const organizationNames = new Map((organizationsQuery.data ?? []).map((organization) => [organization.id, organization.name]));
    const organizationByProject = new Map((projectsQuery.data ?? []).map((project) => [project.id, project.organization_id]));
    const result = new Map<string, string>();
    for (const production of productionsQuery.data ?? []) {
      const organizationId = organizationByProject.get(production.project_id);
      const organizationName = organizationId ? organizationNames.get(organizationId) : undefined;
      if (organizationName) result.set(production.id, organizationName);
    }
    return result;
  }, [organizationsQuery.data, projectsQuery.data, productionsQuery.data]);
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

      <PersonalOverviewSection
        isLoading={dashboardQuery.isLoading}
        isError={dashboardQuery.isError || notificationsQuery.isError}
        error={dashboardQuery.error ?? notificationsQuery.error}
        onRetry={() => { dashboardQuery.refetch(); notificationsQuery.refetch(); }}
        upcomingRehearsals={dashboardViewModel?.upcomingRehearsals ?? []}
        productionOrganizationNames={productionOrganizationNames}
        notifications={unreadNotifications}
      />

      <HomeMembershipApprovalSection organizations={organizationsQuery.data ?? []} />

      <FollowedOrganizationsFeedSection items={dashboardViewModel?.followedOrganizationsFeed ?? []} />
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
function HomeMembershipApprovalSection({ organizations }: { organizations: import('@/types/api').Organization[] }) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();
  const ownerOrganizations = organizations.filter((organization) => organization.current_person_role === 'OWNER');
  const pendingQueries = useQueries({
    queries: ownerOrganizations.map((organization) => ({
      queryKey: ['pending-membership-requests', organization.id],
      queryFn: () => fetchPendingMembershipRequests(apiClient, organization.id),
      enabled: !!organization.id,
    })),
  });

  const approve = useMutation({
    mutationFn: ({ membershipId }: { membershipId: string }) => approveMembershipRequest(apiClient, membershipId),
    onSuccess: (_data, variables) => {
      const request = pendingQueries
        .flatMap((query) => query.data ?? [])
        .find((candidate) => candidate.id === variables.membershipId);
      if (request) {
        queryClient.invalidateQueries({ queryKey: ['pending-membership-requests', request.organization_id] });
      } else {
        queryClient.invalidateQueries({ queryKey: ['pending-membership-requests'] });
      }
    },
  });

  const reject = useMutation({
    mutationFn: ({ membershipId }: { membershipId: string }) => rejectMembershipRequest(apiClient, membershipId),
    onSuccess: (_data, variables) => {
      const request = pendingQueries
        .flatMap((query) => query.data ?? [])
        .find((candidate) => candidate.id === variables.membershipId);
      if (request) {
        queryClient.invalidateQueries({ queryKey: ['pending-membership-requests', request.organization_id] });
      } else {
        queryClient.invalidateQueries({ queryKey: ['pending-membership-requests'] });
      }
    },
  });

  const requests = pendingQueries.flatMap((query, index) =>
    (query.data ?? []).map((request) => ({
      request,
      organization: ownerOrganizations[index],
    }))
  );

  if (requests.length === 0) return null;

  return (
    <ThemedView style={styles.section} testID="home-membership-approval">
      <ThemedText type="subtitle" style={styles.sectionTitle}>参加申請</ThemedText>
      {requests.map(({ request, organization }) => (
        <ThemedView key={request.id} style={styles.card}>
          <ThemedText type="smallBold">{organization?.name ?? '団体'}</ThemedText>
          <ThemedText type="small">
            {[request.person_family_name, request.person_given_name].filter(Boolean).join(' ') || '氏名未設定'}
          </ThemedText>
          <ThemedView style={styles.responseRow}>
            <TouchableOpacity
              testID={`home-membership-approve-${request.id}`}
              onPress={() => approve.mutate({ membershipId: request.id })}
              disabled={approve.isPending || reject.isPending}
              style={styles.approveButton}
            >
              <ThemedText style={styles.approveButtonText}>承認</ThemedText>
            </TouchableOpacity>
            <TouchableOpacity
              testID={`home-membership-reject-${request.id}`}
              onPress={() => reject.mutate({ membershipId: request.id })}
              disabled={approve.isPending || reject.isPending}
              style={styles.rejectButton}
            >
              <ThemedText style={styles.rejectButtonText}>却下</ThemedText>
            </TouchableOpacity>
          </ThemedView>
        </ThemedView>
      ))}
    </ThemedView>
  );
}

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
        おはようございます、{familyName}さん
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

function PersonalOverviewSection({
  isLoading,
  isError,
  error,
  onRetry,
  upcomingRehearsals,
  productionOrganizationNames,
  notifications,
}: {

  isLoading: boolean;
  isError: boolean;
  error: unknown;
  onRetry: () => void;
  upcomingRehearsals: UpcomingRehearsalViewModel[];
  productionOrganizationNames: Map<string, string>;
  notifications: MyNotification[];
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
            今後の予定
          </ThemedText>
          <ThemedView testID="upcoming-rehearsals-list" style={styles.list}>
            {upcomingRehearsals.map((rehearsal) => (
              <UpcomingRehearsalCard
                key={rehearsal.rehearsalId}
                rehearsal={rehearsal}
                organizationName={productionOrganizationNames.get(rehearsal.productionId) ?? null}
              />
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
              <HomeNotificationRow key={notification.id} notification={notification} />
            ))}
          </ThemedView>
        </>
      )}

      <TouchableOpacity testID="home-my-notifications-link" style={styles.myNotificationsLink} onPress={() => router.push('/my-notifications')}>
        <ThemedText type="link">すべてのお知らせを見る</ThemedText>
      </TouchableOpacity>
    </ThemedView>
  );
}

/**
 * StageArt ホームお知らせ・稽古出欠通知 instruction: tapping an unread
 * notification (1) marks it read (`PATCH /me/notifications/{id}/read`,
 * refetch-driven - same Source-of-Truth pattern as every other
 * mark-read flow in this codebase) and, once that succeeds, (2)
 * navigates - to the target Rehearsal's own detail screen when this
 * notification carries both `rehearsal_id` and `production_id` (never
 * by parsing `message`), or to `/my-notifications` otherwise (every
 * other notification type's own existing history destination). Read
 * state is set before navigation ever starts, so even if navigation
 * itself were interrupted, the "read" fact is already persisted
 * server-side - it is never lost.
 */
function HomeNotificationRow({ notification }: { notification: MyNotification }) {
  const router = useRouter();
  const markRead = useMarkMyNotificationRead();

  function handlePress() {
    markRead.mutate(notification.id, {
      onSuccess: () => {
        if (notification.rehearsal_id && notification.production_id) {
          router.push(`/production/${notification.production_id}/schedule/attendance/${notification.rehearsal_id}`);
        } else {
          router.push('/my-notifications');
        }
      },
    });
  }

  return (
    <TouchableOpacity
      testID={`home-notification-row-${notification.id}`}
      style={styles.card}
      onPress={handlePress}
      disabled={markRead.isPending}
    >
      <ThemedText type="smallBold">{notification.message}</ThemedText>
      <ThemedText type="small" themeColor="textSecondary">
        {formatNotificationCreatedAt(notification.created_at)}
      </ThemedText>
    </TouchableOpacity>
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
function formatTimeRange(iso: string): string {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '';
  return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
}

function formatNotificationCreatedAt(iso: string): string {
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return iso;
  return `${d.getFullYear()}/${d.getMonth() + 1}/${d.getDate()} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}

const ACTIVITY_TYPE_LABELS: Record<string, string> = {
  REHEARSAL: '稽古',
  PRACTICE: '練習',
  SELF_PRACTICE: '自主練',
  WORKSHOP: 'ワークショップ',
  MEETING: '会議',
  DISCUSSION: '打ち合わせ',
  EVENT: 'イベント',
  OTHER: 'その他',
};

function activityTypeLabel(activityType: string): string {
  return ACTIVITY_TYPE_LABELS[activityType] ?? activityType;
}

function UpcomingRehearsalCard({
  rehearsal,
  organizationName,
}: {
  rehearsal: UpcomingRehearsalViewModel;
  organizationName: string | null;
}) {
  const router = useRouter();
  const rehearsalQuery = useRehearsal(rehearsal.rehearsalId);
  const phase = rehearsalQuery.data ? phaseForRehearsalStatus(rehearsalQuery.data.status) : undefined;
  const attendancesQuery = useRehearsalAttendances(rehearsal.rehearsalId, phase);
  const respondMutation = useRespondRehearsalAttendance(rehearsal.rehearsalId, phase);
  const currentPersonQuery = useCurrentPerson();
  const myRecord = attendancesQuery.data?.find((a) => a.person_id === currentPersonQuery.data?.id);
  const responseOptions = phase ? responseOptionsForPhase(phase) : [];

  return (
    <ThemedView style={styles.rehearsalCard}>
      <TouchableOpacity
        testID={'upcoming-rehearsal-row-' + rehearsal.rehearsalId}
        onPress={() => router.push('/production/' + rehearsal.productionId + '/schedule/attendance/' + rehearsal.rehearsalId)}
        accessibilityRole="button"
        accessibilityLabel={'予定詳細、' + rehearsal.productionName}
      >
        <ThemedView style={styles.titleRow}>
          {rehearsal.isUnanswered && <ThemedView style={styles.unansweredDot} testID="upcoming-rehearsal-unanswered-dot" />}
          <ThemedText type="default" style={styles.rehearsalProductionName}>{rehearsal.productionName}</ThemedText>
        </ThemedView>
        <ThemedText type="smallBold" style={styles.rehearsalLabel}>{rehearsal.title ?? '予定'}</ThemedText>
        <ThemedView style={styles.rehearsalMeta}>
          {rehearsal.dateDisplay && (
            <ThemedView style={styles.rehearsalDate}>
              <ThemedText type="small" style={styles.rehearsalMetaLine}>
                <ThemedText style={styles.rehearsalMetaLabel}>日時：</ThemedText>
              </ThemedText>
              <ThemedText type="small" style={styles.rehearsalDateText}>
                {rehearsal.dateDisplay}
              </ThemedText>
              {rehearsal.timeDisplay && (
                <ThemedText type="small" style={styles.rehearsalMetaLine}>
                  {rehearsal.timeDisplay}{rehearsal.endTimeDisplay ? '〜' + rehearsal.endTimeDisplay : ''}
                </ThemedText>
              )}
            </ThemedView>
          )}
          {rehearsal.location && (
            <ThemedText type="small" style={styles.rehearsalMetaLine}>
              <ThemedText style={styles.rehearsalMetaLabel}>場所：</ThemedText>
              {rehearsal.location}
            </ThemedText>
          )}
          {rehearsalQuery.data?.description && (
            <ThemedText type="small" style={styles.rehearsalMetaLine}>
              <ThemedText style={styles.rehearsalMetaLabel}>備考：</ThemedText>
              {rehearsalQuery.data.description}
            </ThemedText>
          )}
        </ThemedView>
      </TouchableOpacity>
      {myRecord && responseOptions.length > 0 && (
        <ThemedView style={styles.homeAttendanceArea}>
          <ThemedText type="smallBold" style={styles.attendanceHeading}>出欠</ThemedText>
          {myRecord.status === 'UNANSWERED' ? (
            <ThemedView style={styles.responseButtonRow}>
              {responseOptions.map((option) => {
                const isAttending = option === 'ATTENDING' || option === 'AVAILABLE';
                return (
                  <TouchableOpacity
                    key={option}
                    testID={'home-rehearsal-respond-' + rehearsal.rehearsalId + '-' + option}
                    onPress={() => respondMutation.mutate({ attendanceId: myRecord.id, status: option })}
                    disabled={respondMutation.isPending}
                    style={[styles.responseButton, isAttending ? styles.attendingButton : styles.absentButton, respondMutation.isPending && styles.disabledButton]}
                  >
                    <ThemedText style={styles.actionButtonText}>{statusLabel(option)}</ThemedText>
                  </TouchableOpacity>
                );
              })}
            </ThemedView>
          ) : (
            <ThemedView style={styles.answeredStatus}>
              <ThemedText type="smallBold" themeColor="textSecondary">回答済み</ThemedText>
              <ThemedText type="smallBold" style={styles.currentAttendanceStatus}>
                {statusLabel(myRecord.status)}
              </ThemedText>
            </ThemedView>
          )}
          {respondMutation.isError && <ThemedText themeColor="textSecondary">{getErrorMessage(respondMutation.error)}</ThemedText>}
        </ThemedView>
      )}
    </ThemedView>
  );
}
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
  myNotificationsLink: {
    paddingHorizontal: Spacing.four,
    paddingTop: Spacing.two,
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
  inviteLink: {
    paddingTop: Spacing.two,
  },
  centered: { alignItems: 'center', justifyContent: 'center', padding: Spacing.four, gap: Spacing.two },
  list: { gap: Spacing.two, width: '100%' },
  section: { paddingHorizontal: Spacing.three, paddingBottom: Spacing.two, width: '100%' },
  sectionTitle: { paddingTop: Spacing.one, paddingBottom: Spacing.one, fontSize: 20, lineHeight: 26 },
  rehearsalProductionName: { fontSize: 15, lineHeight: 20, fontWeight: '600' },
  rehearsalTitle: { fontSize: 16, lineHeight: 22, fontWeight: '700' },
  rehearsalType: { fontSize: 13, lineHeight: 18 },
  rehearsalSubTitle: { fontSize: 14, lineHeight: 19 },
  rehearsalMeta: { gap: Spacing.half, paddingTop: Spacing.two },
  rehearsalDate: { paddingBottom: Spacing.half },
  rehearsalDateText: { fontWeight: '600' },
  rehearsalMetaLine: { fontSize: 15, lineHeight: 21, fontWeight: '600' },
  rehearsalMetaLabel: { fontWeight: '700' },
  attendanceHeading: { fontSize: 14, lineHeight: 18 },
  answeredStatus: { flexDirection: 'row', alignItems: 'baseline', gap: Spacing.two, paddingVertical: Spacing.half },
  currentAttendanceStatus: { fontSize: 16, lineHeight: 22 },
  responseRow: { flexDirection: 'row', gap: Spacing.two, marginTop: Spacing.one },
  approveButton: { backgroundColor: '#C6892B', borderRadius: Radius.medium, paddingVertical: Spacing.one, paddingHorizontal: Spacing.three },
  approveButtonText: { color: '#fff', fontWeight: '600' },
  rejectButton: { borderWidth: 1, borderColor: '#C6892B', borderRadius: Radius.medium, paddingVertical: Spacing.one, paddingHorizontal: Spacing.three },
  rejectButtonText: { color: '#C6892B', fontWeight: '600' },
  rehearsalCard: { width: '100%', maxWidth: 680, alignSelf: 'flex-start', borderWidth: 1, borderColor: '#e1dee6', borderRadius: Radius.medium, padding: Spacing.three, gap: Spacing.two, backgroundColor: '#fff' },
  homeAttendanceArea: { borderTopWidth: 1, borderTopColor: '#eee', paddingTop: Spacing.two, gap: Spacing.one },
  responseButtonSelected: { borderWidth: 2, borderColor: '#fff' },
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
  /**
   * StageArt ホームお知らせ・稽古出欠通知 instruction: these 6 style keys
   * were already referenced by this file's own JSX (the お知らせ card and
   * the 稽古予定 self-response row) but missing from this StyleSheet
   * object - a pre-existing gap confirmed via `git stash` (the same
   * `tsc --noEmit` errors reproduce on the unmodified file), not
   * something this round's own edits introduced. Values are copied
   * verbatim from the established precedent for this exact same
   * card/response-button pattern
   * (production/[id]/schedule/attendance/[rehearsalId]/index.tsx's
   * identically-named styles), not a new design decision.
   */
  card: {
    borderWidth: 1,
    borderColor: '#e1dee6',
    borderRadius: 10,
    padding: Spacing.three,
    gap: Spacing.one,
  },
  responseButtonRow: { flexDirection: 'row', gap: Spacing.one, flexWrap: 'wrap', alignItems: 'flex-start' },
  responseButton: {
    minWidth: 88,
    minHeight: 36,
    paddingHorizontal: Spacing.two,
    paddingVertical: Spacing.one,
    borderRadius: 6,
    alignItems: 'center',
    justifyContent: 'center',
  },
  attendingButton: { backgroundColor: '#2e7d32' },
  absentButton: { backgroundColor: '#c62828' },
  disabledButton: { opacity: 0.5 },
  actionButtonText: { color: '#fff', fontWeight: '700', fontSize: 14, lineHeight: 18 },
});
