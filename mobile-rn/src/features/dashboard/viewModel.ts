import { buildNotificationViewModel, type NotificationViewModel } from '@/features/notifications/viewModel';
import { formatDayHeader, formatTime } from '@/features/schedule/groupByDay';
import type { FollowedOrganizationFeedItem, MyDashboard, UpcomingRehearsal } from '@/types/api';

/**
 * §7 of this Phase's instruction: the Backend's `limit=50` on
 * upcoming_rehearsals is a defensive server-side bound, not a UI spec.
 * HOME shows only the nearest few - a named constant here, not a magic
 * number in JSX, so the policy is visible and independently testable.
 */
export const HOME_UPCOMING_REHEARSAL_LIMIT = 3;
export const HOME_NOTIFICATION_LIMIT = 3;
export const HOME_FOLLOWED_ORGANIZATIONS_FEED_LIMIT = 3;

export type UpcomingRehearsalViewModel = {
  rehearsalId: string;
  productionId: string;
  productionName: string;
  title: string | null;
  dateDisplay: string | null;
  timeDisplay: string | null;
  location: string | null;
  isUnanswered: boolean;
};

/**
 * Date/time formatting reuses formatDayHeader()/formatTime() from the
 * existing Schedule feature (§7 "既存Mobileの日時表示ルールを確認して統一
 * する") rather than introducing a second date-formatting convention.
 * `attendance_status` itself is never shown as text (Phase 7.4 §11 /
 * this Phase's §8) - only collapsed to a boolean the row can use for a
 * light, optional visual treatment.
 */
export function buildUpcomingRehearsalViewModel(rehearsal: UpcomingRehearsal): UpcomingRehearsalViewModel {
  const start = rehearsal.start_date_time ? new Date(rehearsal.start_date_time) : null;

  return {
    rehearsalId: rehearsal.rehearsal_id,
    productionId: rehearsal.production_id,
    productionName: rehearsal.production_name,
    title: rehearsal.title,
    dateDisplay: start ? formatDayHeader(start) : null,
    timeDisplay: rehearsal.start_date_time ? formatTime(rehearsal.start_date_time) : null,
    location: rehearsal.location,
    isUnanswered: rehearsal.attendance_status === 'UNANSWERED',
  };
}

/**
 * Composes the shared, already-tested buildNotificationViewModel()
 * (features/notifications/viewModel.ts) rather than duplicating its
 * title-label/date-formatting logic, and adds only what that shared
 * function does not need for its own (already-Production-scoped)
 * screen: productionId, required here so a HOME row can navigate to
 * `/production/{productionId}/notifications` (this Phase's §12).
 */
export type DashboardNotificationViewModel = NotificationViewModel & { productionId: string };

export type FollowedOrganizationFeedItemViewModel = {
  organizationId: string;
  organizationName: string;
  productionId: string;
  productionName: string;
  productionSlug: string | null;
  organizationSlug: string | null;
  dateDisplay: string;
};

/** `dateDisplay` reuses formatDayHeader() (the same "見た目の日付" rule
 * already established for Rehearsals), applied to `published_at` since a
 * Feed row is itself dated by when its Production was published, not by
 * any Rehearsal date. */
export function buildFollowedOrganizationFeedItemViewModel(
  item: FollowedOrganizationFeedItem
): FollowedOrganizationFeedItemViewModel {
  return {
    organizationId: item.organization_id,
    organizationName: item.organization_name,
    productionId: item.production_id,
    productionName: item.production_name,
    productionSlug: item.production_slug,
    organizationSlug: item.organization_slug,
    dateDisplay: formatDayHeader(new Date(item.published_at)),
  };
}

export function buildDashboardViewModel(dashboard: MyDashboard): {
  upcomingRehearsals: UpcomingRehearsalViewModel[];
  notifications: DashboardNotificationViewModel[];
  followedOrganizationsFeed: FollowedOrganizationFeedItemViewModel[];
} {
  return {
    upcomingRehearsals: dashboard.upcoming_rehearsals
      .slice(0, HOME_UPCOMING_REHEARSAL_LIMIT)
      .map(buildUpcomingRehearsalViewModel),
    notifications: dashboard.notifications.slice(0, HOME_NOTIFICATION_LIMIT).map((fact) => ({
      ...buildNotificationViewModel(fact),
      productionId: fact.production_id,
    })),
    followedOrganizationsFeed: dashboard.followed_organizations_feed
      .slice(0, HOME_FOLLOWED_ORGANIZATIONS_FEED_LIMIT)
      .map(buildFollowedOrganizationFeedItemViewModel),
  };
}
