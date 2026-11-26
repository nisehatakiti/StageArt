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
 * After a successful verification, 「ログイン画面へ」 opens the login screen
 * with the verified address already in the e-mail field (handed over in
 * memory - never in the URL).
 */
describe('verify-email -> login: the verified address is pre-filled', () => {
  it('pre-fills the login e-mail with the verified address and keeps it out of the URL', async () => {
    global.fetch = jest.fn(async (input: unknown) => {
      const url = String(input);
      if (url.endsWith('/auth/email/verify')) {
        const body = { success: true, email: 'member@example.com' };
        return { ok: true, status: 200, text: async () => JSON.stringify(body), json: async () => body } as Response;
      }
      throw new Error(`Unmocked fetch: ${url}`);
    });

    const rendered = renderRouter('src/app', { initialUrl: '/verify-email?token=abc123' });

    await waitFor(() => expect(screen.getByTestId('verify-email-success')).toBeVisible());
    fireEvent.press(screen.getByTestId('verify-email-continue'));

    await waitFor(() => expect(screen.getByTestId('login-email').props.value).toBe('member@example.com'));
    // Neither the address nor the token is in the login screen's URL.
    expect(rendered.getPathnameWithParams()).toBe('/login');
    // Login step 1: the address is ready for 次へ (no password field yet).
    expect(screen.getByTestId('login-next')).toBeVisible();
    expect(screen.queryByTestId('login-password')).toBeNull();

    // Editing the pre-filled address: login-email-handoff-editable.test.tsx
    // (renderRouter() does not deliver text input events in this setup).

    // The token is sent to the verify API exactly as before, and only there.
    const calls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(calls.filter((url) => url.endsWith('/auth/email/verify'))).toHaveLength(1);
    const [, init] = (global.fetch as jest.Mock).mock.calls.find(([url]) => String(url).endsWith('/auth/email/verify')) as [unknown, RequestInit & { body: string }];
    expect(JSON.parse(init.body)).toEqual({ token: 'abc123' });
  });
});
