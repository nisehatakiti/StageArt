import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Kept in its own file - see login-flow.test.tsx's docblock for why every
 * renderRouter() call needs a dedicated file in this codebase.
 *
 * A verify response without `email` (an account without an EmailCredential,
 * or an older Backend) leaves the login screen exactly as before: empty.
 */
describe('verify-email -> login without a reported address', () => {
  it('opens the login screen with an empty e-mail field', async () => {
    global.fetch = jest.fn(async (input: unknown) => {
      const url = String(input);
      if (url.endsWith('/auth/email/verify')) {
        return { ok: true, status: 200, text: async () => JSON.stringify({ success: true }), json: async () => ({ success: true }) } as Response;
      }
      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/verify-email?token=abc123' });

    await waitFor(() => expect(screen.getByTestId('verify-email-success')).toBeVisible());
    fireEvent.press(screen.getByTestId('verify-email-continue'));

    await waitFor(() => expect(screen.getByTestId('login-email')).toBeVisible());
    expect(screen.getByTestId('login-email').props.value).toBe('');
  });
});
