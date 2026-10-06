import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import type { MyNotification } from '@/types/api';

import MyNotificationsScreen from '../app/(app)/my-notifications';

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

const unreadRehearsalNotification: MyNotification = {
  id: 'notif-1',
  type: 'rehearsal_response_reminder',
  message: '踊れチュパカブラの2026/09/20の稽古の出欠を回答してください',
  production_id: 'prod-1',
  rehearsal_id: 'rehearsal-1',
  is_read: false,
  created_at: '2026-08-15T09:30:00+09:00',
};

const readRehearsalNotification: MyNotification = {
  ...unreadRehearsalNotification,
  id: 'notif-2',
  is_read: true,
};

const generalNotification: MyNotification = {
  id: 'notif-3',
  type: 'timetable_version_published',
  message: 'タイムテーブルが更新されました',
  production_id: 'prod-1',
  rehearsal_id: null,
  is_read: false,
  created_at: '2026-08-15T09:30:00+09:00',
};

/**
 * StageArt ホームお知らせ・稽古出欠通知 instruction §確認6/7/8: a rehearsal
 * attendance notification's own `rehearsal_id` (never a parsed
 * message/date) drives navigation to that exact Rehearsal's detail
 * screen - from /my-notifications, same as from Home. Mirrors
 * home-dashboard-content.test.tsx's own "mock useRouter directly, assert
 * the exact push() argument" pattern, since this file is testing the
 * same navigation decision from the other screen that offers it.
 */
function renderMyNotifications(notifications: MyNotification[], onMarkRead: (id: string) => void) {
  global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
    const url = String(input);

    if (url.endsWith('/auth/refresh')) {
      return {
        ok: true,
        status: 200,
        text: async () => JSON.stringify({ access_token: 'refreshed-token', token_type: 'Bearer', expires_in: 3600 }),
      } as Response;
    }
    const readMatch = url.match(/\/me\/notifications\/([^/]+)\/read$/);
    if (readMatch && init?.method === 'PATCH') {
      onMarkRead(readMatch[1]);
      const body = { id: readMatch[1], is_read: true };
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
        <MyNotificationsScreen />
      </AuthProvider>
    </QueryClientProvider>
  );
}

describe('My notifications: navigate to the target Rehearsal', () => {
  beforeEach(() => {
    mockPush.mockClear();
  });

  it('tapping an unread rehearsal notification marks it read and navigates to the Rehearsal detail screen', async () => {
    const markReadCalls: string[] = [];
    renderMyNotifications([unreadRehearsalNotification], (id) => markReadCalls.push(id));

    await waitFor(() => expect(screen.getByTestId('my-notification-row-notif-1')).toBeVisible());

    fireEvent.press(screen.getByTestId('my-notification-row-notif-1'));

    await waitFor(() => expect(markReadCalls).toContain('notif-1'));
    await waitFor(() => expect(mockPush).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-1'));
  });

  it('tapping an already-read rehearsal notification navigates without sending another PATCH', async () => {
    const markReadCalls: string[] = [];
    renderMyNotifications([readRehearsalNotification], (id) => markReadCalls.push(id));

    await waitFor(() => expect(screen.getByTestId('my-notification-row-notif-2')).toBeVisible());

    fireEvent.press(screen.getByTestId('my-notification-row-notif-2'));

    await waitFor(() => expect(mockPush).toHaveBeenCalledWith('/production/prod-1/schedule/attendance/rehearsal-1'));
    expect(markReadCalls).toHaveLength(0);
  });

  it('tapping a general notification (no rehearsal_id) marks it read and never navigates to a Rehearsal screen', async () => {
    const markReadCalls: string[] = [];
    renderMyNotifications([generalNotification], (id) => markReadCalls.push(id));

    await waitFor(() => expect(screen.getByTestId('my-notification-row-notif-3')).toBeVisible());

    fireEvent.press(screen.getByTestId('my-notification-row-notif-3'));

    await waitFor(() => expect(markReadCalls).toContain('notif-3'));
    expect(mockPush).not.toHaveBeenCalled();
  });
});
