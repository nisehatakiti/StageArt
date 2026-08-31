import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';
import type { MyDashboard } from '@/types/api';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';
import DashboardScreen from '../app/dashboard';

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
  followed_organizations_feed: [],
};

function renderDashboard() {
  mockFetchRoutes([
    { test: (url) => url.endsWith('/me/dashboard'), status: 200, body: dashboardWithContent },
    { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
  ]);

  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <OrganizationProvider>
          <DashboardScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

/**
 * Same underlying data/navigation contract as home.tsx's
 * PersonalOverviewSection (home-dashboard-content.test.tsx, Phase 7.5) -
 * applied to the Web Dashboard screen. Kept in its own file - see
 * web-dashboard-greeting.test.tsx's docblock for why.
 */
describe('Web Dashboard: 次の稽古・活動予定 / お知らせ', () => {
  beforeEach(() => {
    mockPush.mockClear();
  });

  it('shows the upcoming Rehearsal and tapping navigates to the existing Attendance screen', async () => {
    renderDashboard();

    await waitFor(() => expect(screen.getByTestId('dashboard-upcoming-rehearsal-row-rehearsal-1')).toBeVisible());
    expect(screen.getByText('○○公演2026')).toBeVisible();
    expect(screen.getByText('通し稽古')).toBeVisible();

    fireEvent.press(screen.getByTestId('dashboard-upcoming-rehearsal-row-rehearsal-1'));
    expect(mockPush).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-1');
  });

  it('shows the Notification and tapping navigates to the existing Production Notification screen', async () => {
    renderDashboard();

    await waitFor(() => expect(screen.getByTestId('dashboard-notification-row-notif-1')).toBeVisible());
    expect(screen.getByText('タイムテーブルが更新されました')).toBeVisible();

    fireEvent.press(screen.getByTestId('dashboard-notification-row-notif-1'));
    expect(mockPush).toHaveBeenCalledWith('/production/prod-2/notifications');
  });
});
