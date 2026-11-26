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
 * 仕様書 Case D: no valid email resolvable anywhere.
 */
describe('Account: Notification Email - no email set', () => {
  it('shows "not set" copy when no current email is resolvable', async () => {
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
        const body = { current_email: null, source: 'NONE', pending_email: null };
        return { ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body } as Response;
      }
      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/account' });

    await waitFor(() =>
      expect(screen.getByTestId('account-notification-email-current')).toHaveTextContent('メール通知先は設定されていません')
    );
  });
});
