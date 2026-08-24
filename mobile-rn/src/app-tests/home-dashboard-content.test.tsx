import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';
import type { MyDashboard } from '@/types/api';

import HomeScreen from '../app/home';

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
      end_date_time: null,
      location: '○○スタジオ',
      attendance_status: 'UNANSWERED',
    },
  ],
  notifications: [
    {
      id: 'notif-1',
      type: 'timetable_version_published',
      production_id: 'prod-2',
      rehearsal_id: 'rehearsal-9',
      timetable_id: 'timetable-1',
      version: 1,
      published_by: 'person-1',
      published_at: '2026-08-15T09:30:00+09:00',
      change_summary: null,
      created_at: '2026-08-15T09:30:00+09:00',
      is_read: false,
    },
  ],
};

/**
 * Isolated component render (not renderRouter()) with expo-router's
 * useRouter mocked directly - this verifies exactly which path each
 * Personal Overview row navigates to, without needing to also stand up
 * the destination screens' own data dependencies (those are covered by
 * their own existing test suites, e.g. attendance-detail-full.test.tsx /
 * notifications-feed-full.test.tsx).
 */
function renderHome(dashboard: MyDashboard) {
  global.fetch = jest.fn(async (input: unknown) => {
    const url = String(input);

    if (url.endsWith('/auth/refresh')) {
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify({ access_token: 'refreshed-token', token_type: 'Bearer', expires_in: 3600 }),
      } as Response;
    }

    if (url.endsWith('/me/dashboard')) {
      return { ok: true, status: 200, text: async () => JSON.stringify(dashboard) } as Response;
    }
    if (url.endsWith('/organizations')) {
      return { ok: true, status: 200, text: async () => JSON.stringify([]) } as Response;
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
    expect(screen.getByText('○○公演2026')).toBeVisible();
    expect(screen.getByText('通し稽古')).toBeVisible();
    expect(screen.getByText(/○○スタジオ/)).toBeVisible();
    // attendance_status itself is never shown as text (Phase 7.4 §11).
    expect(screen.queryByText('UNANSWERED')).toBeNull();
    expect(screen.queryByText('未回答')).toBeNull();
    expect(screen.getByTestId('upcoming-rehearsal-unanswered-dot')).toBeVisible();

    fireEvent.press(screen.getByTestId('upcoming-rehearsal-row-rehearsal-1'));

    expect(mockPush).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-1');
  });

  it('shows the Notification with neutral wording (never "自分宛て"/"あなた宛て") and tapping navigates to the existing Production Notification screen', async () => {
    await renderHome(dashboardWithContent);

    await waitFor(() => expect(screen.getByTestId('home-notification-row-notif-1')).toBeVisible());
    expect(screen.getByText('タイムテーブルが更新されました')).toBeVisible();
    expect(screen.queryByText(/自分宛て|あなた宛て/)).toBeNull();

    fireEvent.press(screen.getByTestId('home-notification-row-notif-1'));

    expect(mockPush).toHaveBeenCalledWith('/production/prod-2/notifications');
  });

  /**
   * StageArt Web First Phase 1 (docs/04-HomeRoleBasedMenu.md §02/§10,
   * docs/04-DomainModel/Follow.md): "次回稽古なし"/"お知らせなし" are
   * explicitly prohibited placeholder text - when both lists are empty,
   * Personal Overview renders nothing at all (not an empty-state
   * message, and not an error).
   */
  it('renders nothing for Personal Overview when both lists are empty, not an error', async () => {
    await renderHome({ upcoming_rehearsals: [], notifications: [] });

    await waitFor(() => expect(screen.getByTestId('home-primary-nav')).toBeVisible());
    expect(screen.queryByTestId('upcoming-rehearsals-list')).toBeNull();
    expect(screen.queryByTestId('home-notifications-list')).toBeNull();
    expect(screen.queryByTestId('dashboard-loading')).toBeNull();
    expect(screen.queryByTestId('dashboard-error')).toBeNull();
  });
});
