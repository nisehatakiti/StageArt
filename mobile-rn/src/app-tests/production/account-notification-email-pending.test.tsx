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
 * 仕様書 §8: the actual notification destination stays the current
 * email while a change request is still awaiting verification.
 */
describe('Account: Notification Email - pending change', () => {
  it('shows the pending email while a change request awaits verification', async () => {
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
        const body = { current_email: 'old@example.com', source: 'NOTIFICATION_EMAIL', pending_email: 'new@example.com' };
        return { ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body } as Response;
      }
      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/account' });

    await waitFor(() => expect(screen.getByTestId('account-notification-email-current')).toHaveTextContent('old@example.com'));
    expect(screen.getByTestId('account-notification-email-pending')).toHaveTextContent(
      'new@example.com に確認メールを送信しました。メール内のリンクから確認してください。'
    );
  });
});
