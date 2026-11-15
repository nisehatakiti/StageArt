import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt 招待登録のメール確認省略ラウンド: completing registration via a
 * valid invitation link's token forwards that token to
 * POST /auth/email/register, and the Backend's resulting GET /me already
 * reports email_verified: true for that account (see
 * RegisterWithEmailUseCase's own docblock - the invitation link itself
 * already proved the address is reachable) - so this must land straight
 * on /set-name (a session is already established), never on
 * registration-pending.tsx's "確認メールを送信しました".
 */
describe('register screen: invitation-sourced registration skips email verification', () => {
  it('forwards the invitation token and lands on /set-name instead of registration-pending', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.includes('/participant-invitations/resolve') && u.includes('token=good-token'),
        status: 200,
        body: {
          production_name: '踊れチュパカブラ',
          email: 'invitee@example.com',
          name: '山田 花子',
          participant_type: 'CAST',
          status: 'PENDING',
        },
      },
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
      {
        test: (u) => u.endsWith('/me'),
        status: 200,
        body: { id: 'person-1', word_press_user_id: 1, email_verified: true, family_name: null, given_name: null },
      },
    ]);

    renderRouter('src/app', { initialUrl: '/register?token=good-token' });

    await waitFor(() => expect(screen.getByTestId('register-email').props.value).toBe('invitee@example.com'));

    // §7/AC: the email field is fixed once a valid invitation preview
    // loaded - it cannot be changed to something other than the invited
    // address.
    expect(screen.getByTestId('register-email').props.editable).toBe(false);

    fireEvent.changeText(screen.getByTestId('register-password'), 'password123');
    fireEvent.press(screen.getByTestId('register-submit'));

    await waitFor(() => expect(screen.getByTestId('set-name-submit')).toBeVisible());
    expect(screen.queryByTestId('registration-pending-resend')).toBeNull();

    const registerCall = (global.fetch as jest.Mock).mock.calls.find(([url]) => String(url).endsWith('/auth/email/register'));
    expect(registerCall).toBeDefined();
    const body = JSON.parse(registerCall![1].body as string);
    expect(body.email).toBe('invitee@example.com');
    expect(body.invitation_token).toBe('good-token');
  });
});
