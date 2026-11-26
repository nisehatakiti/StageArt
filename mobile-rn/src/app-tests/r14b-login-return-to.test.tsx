import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B stage 9 (design doc §10 「ログイン」, §14.2, R13 Medium):
 * after login the person goes to return_to when it is an in-app path. (A
 * URL of another site is never followed - see utils/returnTo.test.ts.)
 */
describe('R14-B ログイン: return_to', () => {
  it('returns to /participant-invitations after logging in', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/auth/email/login'),
        status: 200,
        body: { access_token: 'access-token-1', refresh_token: 'refresh-token-1', token_type: 'Bearer', expires_in: 3600 },
      },
      { test: (u) => u.endsWith('/me/participant-invitations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    const router = renderRouter('src/app', { initialUrl: '/login?return_to=%2Fparticipant-invitations' });

    await waitFor(() => expect(screen.getByTestId('login-register-link')).toBeVisible());
    await fireEvent.changeText(screen.getByTestId('login-email'), 'member@example.com');
    await fireEvent.changeText(screen.getByTestId('login-password'), 'password123');
    await fireEvent.press(screen.getByTestId('login-submit'));

    await waitFor(() => expect(router.getPathname()).toBe('/participant-invitations'));
  });
});
