import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const unreadNotification = {
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
};

describe('Notifications tab: mark read rejected by Backend (403)', () => {
  it('shows the existing error pattern and the row stays unread', async () => {
    global.fetch = jest.fn(async (input: unknown, init?: RequestInit) => {
      const url = String(input);

      if (url.endsWith('/auth/refresh')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify({ access_token: 'refreshed-token', token_type: 'Bearer', expires_in: 3600 }),
        } as Response;
      }

      if (url.endsWith('/productions/prod-1')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(productionOne), json: async () => productionOne } as Response;
      }
      if (url.endsWith('/notifications/notif-1/read') && init?.method === 'PATCH') {
        const body = { code: 'stageart_notification_access_denied', message: 'Forbidden' };
        return { ok: false, status: 403, text: async () => JSON.stringify(body), json: async () => body } as Response;
      }
      if (url.includes('/productions/prod-1/notifications')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify([unreadNotification]),
          json: async () => [unreadNotification],
        } as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/production/prod-1/notifications' });

    await waitFor(() => expect(screen.getByTestId('notification-unread-dot')).toBeVisible());

    fireEvent.press(screen.getByTestId('notification-row'));

    await waitFor(() => expect(screen.getByTestId('notifications-mark-read-error')).toBeVisible());
    expect(screen.getByText('この情報を表示する権限がありません。')).toBeVisible();
    // A rejected mark-read must never flip the row to read client-side.
    expect(screen.getByTestId('notification-unread-dot')).toBeVisible();
  });
});
