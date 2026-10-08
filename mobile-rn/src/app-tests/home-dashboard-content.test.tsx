import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';
import type { MyDashboard, MyNotification } from '@/types/api';

import HomeScreen from '../app/(app)/home';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const mockPush = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockPush }),
}));

const dashboardWithContent: MyDashboard = {
  upcoming_rehearsals: [
    {
      rehearsal_id: 'rehearsal-1',
      production_id: 'prod-1',
      production_name: '○○公演2026',
      title: '通し稽古',
      start_date_time: '2026-08-21T19:00:00+09:00',
      end_date_time: '2026-08-21T21:00:00+09:00',
      location: '○○スタジオ',
      attendance_status: 'UNANSWERED',
      activity_type: 'REHEARSAL',
    },
  ],
  // StageArt ホームお知らせ・稽古出欠通知 instruction: home.tsx no longer
  // reads MyDashboard.notifications (that field is TimetableVersionPublished-
  // specific) - the お知らせ section is driven entirely by
  // GET /me/notifications (`myNotifications` below) since the preceding
  // UI round ("fix(home): show unread personal notifications on
  // dashboard"). Kept here only because the type still requires it.
  notifications: [],
  followed_organizations_feed: [],
};

const rehearsalReminderNotification: MyNotification = {
  id: 'notif-1',
  type: 'rehearsal_response_reminder',
  message: '○○公演2026の2026/08/25の稽古の出欠を回答してください',
  production_id: 'prod-2',
  rehearsal_id: 'rehearsal-9',
  is_read: false,
  created_at: '2026-08-15T09:30:00+09:00',
};

const generalNotification: MyNotification = {
  id: 'notif-2',
  type: 'timetable_version_published',
  message: 'タイムテーブルが更新されました',
  production_id: 'prod-3',
  rehearsal_id: null,
  is_read: false,
  created_at: '2026-08-15T09:30:00+09:00',
};

/**
 * Isolated component render (not renderRouter()) with expo-router's
 * useRouter mocked directly - this verifies exactly which path each
 * Personal Overview row navigates to, without needing to also stand up
 * the destination screens' own data dependencies (those are covered by
 * their own existing test suites, e.g. attendance-detail-full.test.tsx /
 * notifications-feed-full.test.tsx).
 */
function renderHome(dashboard: MyDashboard, myNotifications: MyNotification[] = []) {
  let notifications = myNotifications;

  global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
    const url = String(input);

    if (url.endsWith('/auth/refresh')) {
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify({ access_token: 'refreshed-token', token_type: 'Bearer', expires_in: 3600 }),
      } as Response;
    }

    if (url.endsWith('/me')) {
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify({
          id: 'person-1',
          word_press_user_id: 1,
          email_verified: true,
          family_name: 'テスト',
          given_name: '太郎',
        }),
      } as Response;
    }
    if (url.endsWith('/me/dashboard')) {
      return { ok: true, status: 200, text: async () => JSON.stringify(dashboard) } as Response;
    }
    if (url.endsWith('/organizations')) {
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify([{ id: 'org-1', name: '○○演劇団', current_person_role: 'MEMBER' }]),
      } as Response;
    }
    if (url.endsWith('/productions')) {
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify([{ id: 'prod-1', project_id: 'project-1', name: '○○公演2026' }]),
      } as Response;
    }
    if (url.endsWith('/projects')) {
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify([{ id: 'project-1', organization_id: 'org-1' }]),
      } as Response;
    }
    const readMatch = url.match(/\/me\/notifications\/([^/]+)\/read$/);
    if (readMatch && init?.method === 'PATCH') {
      const id = readMatch[1];
      notifications = notifications.map((n) => (n.id === id ? { ...n, is_read: true } : n));
      const body = { id, is_read: true };
      return { ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body } as Response;
    }
    if (url.endsWith('/me/notifications')) {
      return { ok: true, status: 200, text: async () => JSON.stringify(notifications) } as Response;
    }

    throw new Error(`Unmocked fetch: ${url}`);
  });

  const queryClient = new QueryClient();

  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <OrganizationProvider>
          <HomeScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

describe('Home: Personal Overview (Phase 7.5)', () => {
  beforeEach(() => {
    mockPush.mockClear();
  });

  it('shows the upcoming Rehearsal with Production name, title, date/time and location, and tapping navigates to the existing Attendance screen', async () => {
    await renderHome(dashboardWithContent);

    await waitFor(() => expect(screen.getByTestId('upcoming-rehearsal-row-rehearsal-1')).toBeVisible());
    expect(screen.getByText('今後の予定')).toBeVisible();
    expect(screen.getByTestId('home-greeting')).toHaveTextContent('おはようございます、テストさん');
    expect(screen.getByText('【○○演劇団】○○公演2026')).toBeVisible();
    expect(screen.getByText('通し稽古')).toBeVisible();
    expect(screen.getAllByText(/日時：/).length).toBeGreaterThan(0);
    expect(screen.getByText('2026/8/21（金）')).toBeVisible();
    expect(screen.getByText('19:00〜21:00')).toBeVisible();
    expect(screen.getByText(/場所：○○スタジオ/)).toBeVisible();
    // attendance_status itself is never shown as text (Phase 7.4 §11).
    expect(screen.queryByText('UNANSWERED')).toBeNull();
    expect(screen.queryByText('未回答')).toBeNull();
    expect(screen.getByTestId('upcoming-rehearsal-unanswered-dot')).toBeVisible();

    fireEvent.press(screen.getByTestId('upcoming-rehearsal-row-rehearsal-1'));

    expect(mockPush).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-1');
  });

  /**
   * StageArt ホームお知らせ・稽古出欠通知 instruction §確認1-3: an unread
   * notification shows on Home; tapping it (1) marks it read and (2),
   * because this one carries both rehearsal_id and production_id,
   * navigates straight to that Rehearsal's own detail screen - never by
   * parsing `message`.
   */
  it('shows an unread rehearsal attendance notification and tapping marks it read then navigates to the target Rehearsal', async () => {
    await renderHome(dashboardWithContent, [rehearsalReminderNotification]);

    await waitFor(() => expect(screen.getByTestId('home-notification-row-notif-1')).toBeVisible());
    expect(screen.getByText(rehearsalReminderNotification.message)).toBeVisible();

    fireEvent.press(screen.getByTestId('home-notification-row-notif-1'));

    await waitFor(() => expect(mockPush).toHaveBeenCalledWith('/production/prod-2/schedule/attendance/rehearsal-9'));
  });

  /** §確認8: a notification with no rehearsal_id must never be routed to
   * a Rehearsal screen - it falls back to the existing お知らせ history
   * destination. */
  it('tapping a general notification (no rehearsal_id) marks it read then navigates to /my-notifications, never a Rehearsal screen', async () => {
    await renderHome(dashboardWithContent, [generalNotification]);

    await waitFor(() => expect(screen.getByTestId('home-notification-row-notif-2')).toBeVisible());

    fireEvent.press(screen.getByTestId('home-notification-row-notif-2'));

    await waitFor(() => expect(mockPush).toHaveBeenCalledWith('/my-notifications'));
    expect(mockPush).not.toHaveBeenCalledWith(expect.stringContaining('/schedule/attendance/'));
  });

  /** §確認4: once read, a notification disappears from Home's お知らせ
   * list (Home only ever shows unread notifications). */
  it('a notification disappears from the Home list once marked read', async () => {
    await renderHome(dashboardWithContent, [rehearsalReminderNotification]);

    await waitFor(() => expect(screen.getByTestId('home-notification-row-notif-1')).toBeVisible());

    fireEvent.press(screen.getByTestId('home-notification-row-notif-1'));

    await waitFor(() => expect(screen.queryByTestId('home-notification-row-notif-1')).toBeNull());
  });

  /** §確認5: "すべてのお知らせを見る" is a permanent link (not conditional
   * on having unread notifications) once Personal Overview renders at
   * all. */
  it('"すべてのお知らせを見る" is shown even when there are no unread notifications, and navigates to /my-notifications', async () => {
    await renderHome(dashboardWithContent, []);

    await waitFor(() => expect(screen.getByTestId('home-my-notifications-link')).toBeVisible());
    expect(screen.queryByTestId('home-notifications-list')).toBeNull();

    fireEvent.press(screen.getByTestId('home-my-notifications-link'));

    expect(mockPush).toHaveBeenCalledWith('/my-notifications');
  });

  /**
   * StageArt Web First Phase 1 (docs/04-HomeRoleBasedMenu.md §02/§10,
   * docs/04-DomainModel/Follow.md): "次回稽古なし"/"お知らせなし" are
   * explicitly prohibited placeholder text - when both lists are empty,
   * Personal Overview renders nothing at all (not an empty-state
   * message, and not an error).
   */
  it('renders nothing for Personal Overview when both lists are empty, not an error', async () => {
    await renderHome({ upcoming_rehearsals: [], notifications: [], followed_organizations_feed: [] }, []);

    await waitFor(() => expect(screen.queryByTestId('dashboard-loading')).toBeNull());
    expect(screen.queryByTestId('upcoming-rehearsals-list')).toBeNull();
    expect(screen.queryByTestId('home-notifications-list')).toBeNull();
    expect(screen.queryByTestId('home-my-notifications-link')).toBeNull();
    expect(screen.queryByTestId('dashboard-error')).toBeNull();
  });
});

describe('Home: フォロー中の新着 (StageArt Follow)', () => {
  beforeEach(() => {
    mockPush.mockClear();
  });

  it('shows a followed Organization publishing a new Production, and tapping navigates to its public page', async () => {
    await renderHome(
      {
        upcoming_rehearsals: [],
        notifications: [],
        followed_organizations_feed: [
          {
            organization_id: 'org-1',
            organization_name: '○○演劇団',
            organization_slug: 'organization-1',
            production_id: 'prod-1',
            production_name: '新作公演',
            production_slug: 'new-production',
            published_at: '2026-08-15T09:30:00+09:00',
          },
        ],
      },
      []
    );

    await waitFor(() => expect(screen.getByTestId('followed-organization-feed-row-prod-1')).toBeVisible());
    expect(screen.getByText('○○演劇団')).toBeVisible();
    expect(screen.getByText(/新作公演/)).toBeVisible();

    fireEvent.press(screen.getByTestId('followed-organization-feed-row-prod-1'));

    expect(mockPush).toHaveBeenCalledWith('/organization-1/new-production');
  });

  it('renders nothing when nothing is followed, not an empty-state message', async () => {
    await renderHome({ upcoming_rehearsals: [], notifications: [], followed_organizations_feed: [] }, []);

    await waitFor(() => expect(screen.queryByTestId('dashboard-loading')).toBeNull());
    expect(screen.queryByTestId('followed-organizations-feed-section')).toBeNull();
    expect(screen.queryByText(/フォローなし/)).toBeNull();
  });
});
