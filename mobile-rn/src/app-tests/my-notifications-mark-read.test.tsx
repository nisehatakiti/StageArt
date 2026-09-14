import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson } from '@/features/attendance/__fixtures__/attendanceFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const unreadNotification = {
  id: 'notif-1',
  type: 'rehearsal_cancelled',
  message: 'Show の2026/09/20の稽古は中止となりました',
  production_id: 'prod-1',
  is_read: false,
  created_at: '2026-08-15T09:30:00+09:00',
};

/**
 * Notification基盤実装 phase §3/§15: the new personal お知らせ screen
 * (GET /me/notifications, PATCH /me/notifications/{id}/read) - mirrors
 * the existing Production-scoped notifications tab's own mark-read test
 * pattern (refetch-driven, not a local optimistic mutation).
 */
describe('My notifications: mark read', () => {
  it('shows the message and sends PATCH on tap, clearing the unread indicator after refetch', async () => {
    let isRead = false;

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
        return { ok: true, status: 200, text: async () => JSON.stringify(currentPerson), json: async () => currentPerson } as Response;
      }
      if (url.endsWith('/me/notifications/notif-1/read') && init?.method === 'PATCH') {
        isRead = true;
        const body = { id: 'notif-1', is_read: true };
        return { ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body } as Response;
      }
      if (url.endsWith('/me/notifications')) {
        const body = [{ ...unreadNotification, is_read: isRead }];
        return { ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body } as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/my-notifications' });

    await waitFor(() => expect(screen.getByTestId('my-notification-message')).toHaveTextContent('Show の2026/09/20の稽古は中止となりました'));
    expect(screen.getByTestId('my-notification-unread-dot')).toBeVisible();

    fireEvent.press(screen.getByTestId('my-notification-row-notif-1'));

    await waitFor(() => expect(screen.queryByTestId('my-notification-unread-dot')).toBeNull());
  });
});
