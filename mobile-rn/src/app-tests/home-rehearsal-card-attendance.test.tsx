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

const rehearsal = {
  id: 'rehearsal-1',
  production_id: 'prod-1',
  title: null,
  description: '台本と筆記用具を持参',
  start_date_time: '2026-08-21T19:00:00+09:00',
  end_date_time: '2026-08-21T21:00:00+09:00',
  timezone: 'Asia/Tokyo',
  location: '○○スタジオ',
  status: 'CONFIRMED',
  activity_type: 'REHEARSAL',
  response_deadline: null,
  created_at: '',
  updated_at: '',
};

const myAttendance = {
  id: 'attendance-1',
  rehearsal_id: 'rehearsal-1',
  person_id: 'person-1',
  phase: 'ATTENDANCE_CONFIRMATION',
  status: 'UNANSWERED',
  remarks: null,
  created_at: '',
  updated_at: '',
};

function renderHome(
  dashboard: MyDashboard,
  myNotifications: MyNotification[] = [],
  myAttendanceStatus = 'UNANSWERED',
  rehearsalBody: Omit<typeof rehearsal, 'response_deadline'> & { response_deadline: string | null } = rehearsal
) {
  let notifications = myNotifications;
  let attendanceStatus = myAttendanceStatus;

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
    if (url.endsWith('/rehearsals/rehearsal-1')) {
      return { ok: true, status: 200, text: async () => JSON.stringify(rehearsalBody) } as Response;
    }
    if (url.includes('/rehearsal-attendances/attendance-1/respond') && init?.method === 'PUT') {
      attendanceStatus = JSON.parse(String(init.body)).status;
      const body = { ...myAttendance, status: attendanceStatus };
      return { ok: true, status: 200, text: async () => JSON.stringify(body) } as Response;
    }
    if (url.includes('/rehearsals/rehearsal-1/attendances')) {
      return { ok: true, status: 200, text: async () => JSON.stringify([{ ...myAttendance, status: attendanceStatus }]) } as Response;
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

/**
 * PR #7 R8 (2-3): the Home 予定 card shows the start/end time and 備考. The
 * member's own answer is shown once answered (no buttons); the answer
 * buttons appear only while unanswered. Pressing an answer button answers
 * in place and does not open the card; the card body (title etc.) opens
 * the existing Attendance (予定詳細) screen.
 */
describe('Home: 予定 card attendance', () => {
  beforeEach(() => {
    mockPush.mockClear();
  });

  it('unanswered: shows the buttons; answering does not navigate and then shows the answer without buttons', async () => {
    renderHome(dashboardWithContent);

    await waitFor(() => expect(screen.getByTestId('home-rehearsal-respond-rehearsal-1-ATTENDING')).toBeVisible());
    expect(screen.getByTestId('home-rehearsal-respond-rehearsal-1-NOT_ATTENDING')).toBeVisible();
    expect(screen.getByText('19:00〜21:00')).toBeVisible();
    expect(screen.getByText(/台本と筆記用具を持参/)).toBeVisible();

    fireEvent.press(screen.getByTestId('home-rehearsal-respond-rehearsal-1-ATTENDING'));

    await waitFor(() => expect(screen.getByText('回答済み')).toBeVisible());
    expect(screen.getByText('出席')).toBeVisible();
    expect(screen.queryByTestId('home-rehearsal-respond-rehearsal-1-ATTENDING')).toBeNull();
    expect(screen.queryByTestId('home-rehearsal-respond-rehearsal-1-NOT_ATTENDING')).toBeNull();
    expect(mockPush).not.toHaveBeenCalled();

    const respondCall = (global.fetch as jest.Mock).mock.calls.find(([url]) =>
      String(url).includes('/rehearsal-attendances/attendance-1/respond')
    );
    expect(JSON.parse(String(respondCall?.[1]?.body))).toMatchObject({ status: 'ATTENDING' });
  });

  it('answered: shows the answer without buttons, and the card body opens the Attendance screen', async () => {
    renderHome(dashboardWithContent, [], 'NOT_ATTENDING');

    await waitFor(() => expect(screen.getByText('回答済み')).toBeVisible());
    expect(screen.getByText('欠席')).toBeVisible();
    expect(screen.queryByTestId('home-rehearsal-respond-rehearsal-1-ATTENDING')).toBeNull();

    fireEvent.press(screen.getByText('回答済み'));
    expect(mockPush).not.toHaveBeenCalled();

    fireEvent.press(screen.getByTestId('upcoming-rehearsal-row-rehearsal-1'));
    expect(mockPush).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-1');
  });

  /**
   * PR #7 R9 (§8.2): past the 調整中 response deadline the Backend rejects
   * a new answer, so the card says so instead of offering buttons.
   */
  it('調整中で回答期限を過ぎた未回答: ボタンを出さず期限切れを表示する', async () => {
    renderHome(dashboardWithContent, [], 'UNANSWERED', {
      ...rehearsal,
      status: 'SCHEDULED',
      response_deadline: '2020-01-01T00:00:00+09:00',
    });

    await waitFor(() => expect(screen.getByTestId('home-rehearsal-deadline-passed-rehearsal-1')).toBeVisible());
    expect(screen.getByText('未回答（回答期限を過ぎました）')).toBeVisible();
    expect(screen.queryByTestId('home-rehearsal-respond-rehearsal-1-AVAILABLE')).toBeNull();
    expect(screen.queryByTestId('home-rehearsal-respond-rehearsal-1-UNAVAILABLE')).toBeNull();
  });

  it('調整中で回答期限前の未回答: 参加可能／参加不可のボタンを出す', async () => {
    renderHome(dashboardWithContent, [], 'UNANSWERED', {
      ...rehearsal,
      status: 'SCHEDULED',
      response_deadline: '2099-01-01T00:00:00+09:00',
    });

    await waitFor(() => expect(screen.getByTestId('home-rehearsal-respond-rehearsal-1-AVAILABLE')).toBeVisible());
    expect(screen.getByTestId('home-rehearsal-respond-rehearsal-1-UNAVAILABLE')).toBeVisible();
    expect(screen.queryByTestId('home-rehearsal-deadline-passed-rehearsal-1')).toBeNull();
  });
});
