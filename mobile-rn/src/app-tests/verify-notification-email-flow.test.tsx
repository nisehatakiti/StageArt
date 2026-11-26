import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Kept in its own file - see login-flow.test.tsx's docblock for why
 * every renderRouter() call needs a dedicated file in this codebase
 * (verify-notification-email-flow-error.test.tsx is the sibling test
 * for the error/retry path).
 *
 * 通知用Email確認・変更機能 §17/§21: deep-link landing screen for
 * WordPressAuthMailer.php's
 * sendNotificationEmailChangeVerificationEmail() link. Public endpoint
 * call (POST /notification-email/verify) - no stored session needed,
 * matching the mail-app-tap scenario.
 */
describe('verify-notification-email screen', () => {
  it('automatically verifies the token from the deep link param and shows success', async () => {
    global.fetch = jest.fn(async (input: unknown) => {
      const url = String(input);
      if (url.endsWith('/notification-email/verify')) {
        return { ok: true, status: 200, text: async () => JSON.stringify({ success: true }), json: async () => ({ success: true }) } as Response;
      }
      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/verify-notification-email?token=abc123' });

    await waitFor(() => expect(screen.getByTestId('verify-notification-email-success')).toBeVisible());

    const verifyCall = (global.fetch as jest.Mock).mock.calls.find(([url]) => String(url).endsWith('/notification-email/verify'));
    expect(verifyCall).toBeDefined();
    const [, init] = verifyCall as [unknown, RequestInit & { body: string }];
    expect(JSON.parse(init.body)).toEqual({ token: 'abc123' });
  });
});
