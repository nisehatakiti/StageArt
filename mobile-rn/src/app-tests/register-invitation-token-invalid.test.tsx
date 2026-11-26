import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B (design doc §9.2, confirmed by the user; expectation updated
 * for that spec change - previously an invalid token fell back to the
 * blank registration form): an old link with any token, valid or not, is
 * not looked up. A logged-out visitor is moved to the login screen with the
 * fixed return_to=/participant-invitations and without the token, and
 * nothing about an invitation is shown.
 */
describe('register screen: invalid invitation token', () => {
  it('moves a logged-out visitor to login without looking the token up', async () => {
    mockFetchRoutes([]);

    const router = renderRouter('src/app', { initialUrl: '/register?token=bad-token' });

    await waitFor(() => expect(screen.getByTestId('login-register-link')).toBeVisible());
    expect(router.getPathname()).toBe('/login');
    expect(router.getSearchParams()).toEqual({ return_to: '/participant-invitations' });
    expect(router.getPathnameWithParams()).not.toContain('bad-token');
    expect(screen.queryByTestId('register-invitation-notice')).toBeNull();
    const calls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(calls.filter((url) => url.includes('bad-token') || url.includes('/participant-invitations'))).toEqual([]);
  });
});
