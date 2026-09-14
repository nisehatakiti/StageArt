import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Kept in its own file - see login-flow.test.tsx's docblock for why
 * every renderRouter() call needs a dedicated file in this codebase.
 * Render-level check only, matching verify-email-flow-error.test.tsx's
 * own established convention.
 */
describe('verify-notification-email screen: error path', () => {
  it('shows an error state with a retry action when the token is rejected', async () => {
    global.fetch = jest.fn(async (input: unknown) => {
      const url = String(input);
      if (url.endsWith('/notification-email/verify')) {
        return {
          ok: false,
          status: 401,
          text: async () => JSON.stringify({ message: 'トークンが無効です。' }),
          json: async () => ({ message: 'トークンが無効です。' }),
        } as Response;
      }
      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/verify-notification-email?token=expired-token' });

    await waitFor(() => expect(screen.getByTestId('verify-notification-email-error')).toBeVisible());
    expect(screen.getByTestId('verify-notification-email-retry')).toBeVisible();
  });
});
