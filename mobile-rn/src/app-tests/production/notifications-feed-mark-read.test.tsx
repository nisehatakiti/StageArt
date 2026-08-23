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

/**
 * Phase 7.1: tapping an unread row sends PATCH /notifications/{id}/read,
 * then the list is refetched (not locally mutated) so the unread dot
 * disappearing is proof the Backend's own response drove the UI change,
 * not a client-side guess.
 */
describe('Notifications tab: mark read', () => {
  it('sends PATCH on tap and the unread indicator is gone after refetch', async () => {
    let markReadCalled = false;
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

      if (url.endsWith('/productions/prod-1')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(productionOne), json: async () => productionOne } as Response;
      }
      if (url.endsWith('/notifications/notif-1/read') && init?.method === 'PATCH') {
        markReadCalled = true;
        isRead = true;
        const body = { id: 'notif-1', is_read: true };
        return { ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body } as Response;
      }
      if (url.includes('/productions/prod-1/notifications')) {
        const body = [{ ...unreadNotification, is_read: isRead }];
        return { ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body } as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/production/prod-1/notifications' });

    await waitFor(() => expect(screen.getByTestId('notification-unread-dot')).toBeVisible());

    fireEvent.press(screen.getByTestId('notification-row'));

    await waitFor(() => expect(markReadCalled).toBe(true));
    await waitFor(() => expect(screen.queryByTestId('notification-unread-dot')).toBeNull());
  });
});
