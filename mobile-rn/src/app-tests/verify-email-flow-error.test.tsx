import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Kept in its own file - see login-flow.test.tsx's docblock for why
 * every renderRouter() call needs a dedicated file in this codebase.
 *
 * A render-level check only - the retry button's own onPress
 * (setState('idle') to show the manual-entry form again) is simple,
 * directly-reviewable local state and not exercised via a full press
 * interaction here, matching this codebase's established convention
 * for local-state-only outcomes under renderRouter() (see login-
 * flow.test.tsx's docblock and mypage-account-linking-render.test.tsx's
 * docblock for the same reasoning applied elsewhere).
 */
describe('verify-email screen: error path', () => {
  it('shows an error state with a retry action when the token is rejected', async () => {
    global.fetch = jest.fn(async (input: unknown) => {
      const url = String(input);
      if (url.endsWith('/auth/email/verify')) {
        return {
          ok: false,
          status: 422,
          text: async () => JSON.stringify({ message: 'トークンが無効です。' }),
          json: async () => ({ message: 'トークンが無効です。' }),
        } as Response;
      }
      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/verify-email?token=expired-token' });

    await waitFor(() => expect(screen.getByTestId('verify-email-error')).toBeVisible());
    expect(screen.getByTestId('verify-email-retry')).toBeVisible();
  });
});
