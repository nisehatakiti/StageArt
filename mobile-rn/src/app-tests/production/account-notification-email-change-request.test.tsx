import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { currentPerson } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Kept in its own file - see login-flow.test.tsx's docblock for why
 * every renderRouter() call needs a dedicated file in this codebase.
 *
 * A pure render check, matching mypage-account-linking-render.test.tsx's
 * own established convention for this exact toggle-a-form pattern: the
 * "変更する" toggle button's presence is verified here; the actual
 * change-request submission behavior is covered directly against the
 * underlying function in src/features/notificationEmail/api.test.ts,
 * since a full type-then-submit interaction under renderRouter() hits
 * this codebase's documented local-state-press limitation (see that
 * render test's own docblock, and verify-email-flow-error.test.tsx's).
 */
describe('Account: Notification Email - change form toggle', () => {
  it('shows the "変更する" action for the current notification email', async () => {
    global.fetch = jest.fn(async (input: unknown) => {
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
      if (url.endsWith('/me/notification-email')) {
        const body = { current_email: 'old@example.com', source: 'NOTIFICATION_EMAIL', pending_email: null };
        return { ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body } as Response;
      }
      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/account' });

    await waitFor(() => expect(screen.getByTestId('account-notification-email-current')).toHaveTextContent('old@example.com'));
    expect(screen.getByTestId('account-notification-email-toggle')).toBeVisible();
  });
});
