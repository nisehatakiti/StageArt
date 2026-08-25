import type { FollowedOrganizationFeedItem, MyDashboard, NotificationFact, UpcomingRehearsal } from '@/types/api';

import {
  buildDashboardViewModel,
  buildFollowedOrganizationFeedItemViewModel,
  buildUpcomingRehearsalViewModel,
  HOME_FOLLOWED_ORGANIZATIONS_FEED_LIMIT,
  HOME_NOTIFICATION_LIMIT,
  HOME_UPCOMING_REHEARSAL_LIMIT,
} from './viewModel';

function makeRehearsal(overrides: Partial<UpcomingRehearsal> = {}): UpcomingRehearsal {
  return {
    rehearsal_id: 'rehearsal-1',
    production_id: 'prod-1',
    production_name: '○○公演2026',
    title: '稽古',
    start_date_time: '2026-08-21T19:00:00+09:00',
    end_date_time: null,
    location: '○○スタジオ',
    attendance_status: 'UNANSWERED',
    ...overrides,
  };
}

function makeNotification(overrides: Partial<NotificationFact> = {}): NotificationFact {
  return {
    id: 'notif-1',
    type: 'timetable_version_published',
    production_id: 'prod-1',
    rehearsal_id: 'rehearsal-1',
    timetable_id: 'timetable-1',
    version: 1,
    published_by: 'person-1',
    published_at: '2026-08-15T09:30:00+09:00',
    change_summary: null,
    created_at: '2026-08-15T09:30:00+09:00',
    is_read: false,
    ...overrides,
  };
}

function makeFollowedOrganizationFeedItem(
  overrides: Partial<FollowedOrganizationFeedItem> = {}
): FollowedOrganizationFeedItem {
  return {
    organization_id: 'org-1',
    organization_name: '○○演劇団',
    organization_slug: 'organization-1',
    production_id: 'prod-1',
    production_name: '新作公演',
    production_slug: 'new-production',
    published_at: '2026-08-15T09:30:00+09:00',
    ...overrides,
  };
}

describe('buildUpcomingRehearsalViewModel', () => {
  it('maps identifying and display fields, never exposing rehearsal_id as display text separately from navigation use', () => {
    const vm = buildUpcomingRehearsalViewModel(makeRehearsal());

    expect(vm.rehearsalId).toBe('rehearsal-1');
    expect(vm.productionId).toBe('prod-1');
    expect(vm.productionName).toBe('○○公演2026');
    expect(vm.title).toBe('稽古');
    expect(vm.location).toBe('○○スタジオ');
    expect(vm.dateDisplay).toContain('2026');
    expect(vm.timeDisplay).toBe('19:00');
  });

  it('UNANSWERED maps to isUnanswered=true', () => {
    expect(buildUpcomingRehearsalViewModel(makeRehearsal({ attendance_status: 'UNANSWERED' })).isUnanswered).toBe(true);
  });

  it('any other attendance_status maps to isUnanswered=false', () => {
    expect(buildUpcomingRehearsalViewModel(makeRehearsal({ attendance_status: 'AVAILABLE' })).isUnanswered).toBe(false);
  });

  it('a null start_date_time produces null date/time display rather than throwing', () => {
    const vm = buildUpcomingRehearsalViewModel(makeRehearsal({ start_date_time: null }));

    expect(vm.dateDisplay).toBeNull();
    expect(vm.timeDisplay).toBeNull();
  });
});

describe('buildDashboardViewModel: HOME display limits', () => {
  it(`caps upcoming_rehearsals at ${HOME_UPCOMING_REHEARSAL_LIMIT}, keeping the nearest ones in order`, () => {
    const dashboard: MyDashboard = {
      upcoming_rehearsals: [
        makeRehearsal({ rehearsal_id: 'r1' }),
        makeRehearsal({ rehearsal_id: 'r2' }),
        makeRehearsal({ rehearsal_id: 'r3' }),
        makeRehearsal({ rehearsal_id: 'r4' }),
        makeRehearsal({ rehearsal_id: 'r5' }),
      ],
      notifications: [],
      followed_organizations_feed: [],
    };

    const vm = buildDashboardViewModel(dashboard);

    expect(vm.upcomingRehearsals).toHaveLength(HOME_UPCOMING_REHEARSAL_LIMIT);
    expect(vm.upcomingRehearsals.map((r) => r.rehearsalId)).toEqual(['r1', 'r2', 'r3']);
  });

  it(`caps notifications at ${HOME_NOTIFICATION_LIMIT}`, () => {
    const dashboard: MyDashboard = {
      upcoming_rehearsals: [],
      notifications: [
        makeNotification({ id: 'n1' }),
        makeNotification({ id: 'n2' }),
        makeNotification({ id: 'n3' }),
        makeNotification({ id: 'n4' }),
      ],
      followed_organizations_feed: [],
    };

    const vm = buildDashboardViewModel(dashboard);

    expect(vm.notifications).toHaveLength(HOME_NOTIFICATION_LIMIT);
    expect(vm.notifications.map((n) => n.id)).toEqual(['n1', 'n2', 'n3']);
  });

  it('carries productionId through onto each notification view model for HOME navigation', () => {
    const dashboard: MyDashboard = {
      upcoming_rehearsals: [],
      notifications: [makeNotification({ id: 'n1', production_id: 'prod-9' })],
      followed_organizations_feed: [],
    };

    const vm = buildDashboardViewModel(dashboard);

    expect(vm.notifications[0].productionId).toBe('prod-9');
  });

  it('does not fabricate a "自分宛て"/"あなた宛て" phrase anywhere in the notification title', () => {
    const dashboard: MyDashboard = {
      upcoming_rehearsals: [],
      notifications: [makeNotification()],
      followed_organizations_feed: [],
    };

    const vm = buildDashboardViewModel(dashboard);

    expect(vm.notifications[0].title).not.toMatch(/自分宛て|あなた宛て|あなたへ/);
  });

  it(`caps followed_organizations_feed at ${HOME_FOLLOWED_ORGANIZATIONS_FEED_LIMIT}, keeping the earliest ones in order`, () => {
    const dashboard: MyDashboard = {
      upcoming_rehearsals: [],
      notifications: [],
      followed_organizations_feed: [
        makeFollowedOrganizationFeedItem({ production_id: 'p1' }),
        makeFollowedOrganizationFeedItem({ production_id: 'p2' }),
        makeFollowedOrganizationFeedItem({ production_id: 'p3' }),
        makeFollowedOrganizationFeedItem({ production_id: 'p4' }),
      ],
    };

    const vm = buildDashboardViewModel(dashboard);

    expect(vm.followedOrganizationsFeed).toHaveLength(HOME_FOLLOWED_ORGANIZATIONS_FEED_LIMIT);
    expect(vm.followedOrganizationsFeed.map((f) => f.productionId)).toEqual(['p1', 'p2', 'p3']);
  });
});

describe('buildFollowedOrganizationFeedItemViewModel', () => {
  it('carries organization/production identity through for navigation to the public pages', () => {
    const vm = buildFollowedOrganizationFeedItemViewModel(
      makeFollowedOrganizationFeedItem({
        organization_id: 'org-9',
        organization_slug: 'org-nine',
        production_id: 'prod-9',
        production_slug: 'prod-nine',
      })
    );

    expect(vm.organizationId).toBe('org-9');
    expect(vm.organizationSlug).toBe('org-nine');
    expect(vm.productionId).toBe('prod-9');
    expect(vm.productionSlug).toBe('prod-nine');
  });

  it('formats published_at using the same day-header rule as Rehearsal dates', () => {
    const vm = buildFollowedOrganizationFeedItemViewModel(
      makeFollowedOrganizationFeedItem({ published_at: '2026-08-15T09:30:00+09:00' })
    );

    expect(vm.dateDisplay.length).toBeGreaterThan(0);
  });
});
