import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B (design doc §10 「ログイン」; approved fix A), through the real
 * router: a person without a name who came from the invitation list logs
 * in, is shown the name screen first, and that screen carries
 * return_to=/participant-invitations (where it continues after saving -
 * r14b-set-name-return-to.test.tsx).
 */
describe('R14-B ログイン: 名前未設定でも return_to を引き継ぐ', () => {
  it('lands on set-name with return_to', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/auth/email/login'),
        status: 200,
        body: { access_token: 'access-token-1', refresh_token: 'refresh-token-1', token_type: 'Bearer', expires_in: 3600 },
      },
      {
        test: (u) => u.endsWith('/me'),
        status: 200,
        body: { id: 'person-1', word_press_user_id: 1, email_verified: true, family_name: null, given_name: null },
      },
    ]);

    const router = renderRouter('src/app', { initialUrl: '/login?return_to=%2Fparticipant-invitations' });

    await waitFor(() => expect(screen.getByTestId('login-register-link')).toBeVisible());
    await fireEvent.changeText(screen.getByTestId('login-email'), 'member@example.com');
    await fireEvent.changeText(screen.getByTestId('login-password'), 'password123');
    await fireEvent.press(screen.getByTestId('login-submit'));

    await waitFor(() => expect(router.getPathname()).toBe('/set-name'));
    expect(router.getSearchParams()).toEqual({ return_to: '/participant-invitations' });
    expect(screen.getByTestId('set-name-submit')).toBeVisible();
  });
});
