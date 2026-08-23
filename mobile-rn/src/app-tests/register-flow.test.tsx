import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Kept in its own file - see login-flow.test.tsx's docblock for why
 * every renderRouter() call needs a dedicated file in this codebase.
 *
 * Blueprint v1.5 alignment Phase: "呼んだ登録APIが成功した" is no longer
 * treated as "正式な登録が完了した" - a successful POST /auth/email/register
 * now lands on registration-pending.tsx (確認メールを送信しました), not
 * directly on Home. GET /me's email_verified (not exercised by this
 * particular test) is what later gates Home access on a subsequent app
 * entry - see registration-pending-flow.test.tsx / index-email-
 * verification-gate.test.tsx for that half of the flow.
 */
describe('register flow', () => {
  it('navigates from /login to /register, and a successful registration lands on the registration-pending screen (not /home)', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/auth/email/register'),
        status: 201,
        body: {
          access_token: 'access-token-1',
          refresh_token: 'refresh-token-1',
          token_type: 'Bearer',
          expires_in: 3600,
          person_id: 'person-1',
          user_account_id: 'account-1',
          is_new_user: true,
        },
      },
    ]);

    renderRouter('src/app', { initialUrl: '/login' });

    await waitFor(() => expect(screen.getByTestId('login-register-link')).toBeVisible());
    await fireEvent.press(screen.getByTestId('login-register-link'));

    await waitFor(() => expect(screen.getByTestId('register-email')).toBeVisible());
    await fireEvent.changeText(screen.getByTestId('register-email'), 'new-person@example.com');
    await fireEvent.changeText(screen.getByTestId('register-password'), 'password123');
    await fireEvent.press(screen.getByTestId('register-submit'));

    // The registration-pending screen's own display of the passed-along
    // email is covered directly (via a fresh initialUrl, not a typed-
    // then-immediately-submitted transition) by
    // registration-pending-resend.test.tsx / registration-pending-wrong-
    // email.test.tsx - this test's job is only to confirm the flow lands
    // there instead of /home.
    await waitFor(() => expect(screen.getByTestId('registration-pending-resend')).toBeVisible());
    expect(screen.queryByTestId('home-primary-nav')).toBeNull();

    const registerCall = (global.fetch as jest.Mock).mock.calls.find(([url]) => String(url).endsWith('/auth/email/register'));
    expect(registerCall).toBeDefined();
  });
});
