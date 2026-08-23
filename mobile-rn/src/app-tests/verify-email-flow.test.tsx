import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Kept in its own file - see login-flow.test.tsx's docblock for why
 * every renderRouter() call needs a dedicated file in this codebase
 * (verify-email-flow-error.test.tsx is the sibling test for the error/
 * retry path, split out for the same reason).
 *
 * Deep-link landing screen (WordPressAuthMailer.php's
 * stageart://verify-email?token=... - see verify-email.tsx's own
 * docblock). This is a public endpoint call (POST /auth/email/verify),
 * so no stored session/Bearer Token is needed to reach or use this
 * screen - matches the mail-app-tap scenario where the user may not
 * even be logged in on the device that opens the link.
 */
describe('verify-email screen', () => {
  it('automatically verifies the token from the deep link param and shows success', async () => {
    global.fetch = jest.fn(async (input: unknown) => {
      const url = String(input);
      if (url.endsWith('/auth/email/verify')) {
        return { ok: true, status: 200, text: async () => JSON.stringify({ success: true }), json: async () => ({ success: true }) } as Response;
      }
      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/verify-email?token=abc123' });

    await waitFor(() => expect(screen.getByTestId('verify-email-success')).toBeVisible());

    const verifyCall = (global.fetch as jest.Mock).mock.calls.find(([url]) => String(url).endsWith('/auth/email/verify'));
    expect(verifyCall).toBeDefined();
    const [, init] = verifyCall as [unknown, RequestInit & { body: string }];
    expect(JSON.parse(init.body)).toEqual({ token: 'abc123' });
  });
});
