import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B (design doc §9.2 / §10 / §14.2, U5 C案; confirmed by the
 * user): an old invitation link `/register?token=...` opened while logged
 * out goes to the login screen with the fixed
 * return_to=/participant-invitations. The token is not read or looked up
 * (no request is made with it), it is not carried into the URL or
 * return_to, no registration form is shown and nothing about any
 * invitation is shown.
 * (The registration form itself - empty address, typed address sent
 * without a token - is checked in r14b-register-screen.test.tsx.)
 */
describe('register screen: old invitation link while logged out', () => {
  it('goes to login with the fixed return_to, without the token, and shows nothing of an invitation', async () => {
    mockFetchRoutes([]);

    const router = renderRouter('src/app', { initialUrl: '/register?token=good-token' });

    await waitFor(() => expect(screen.getByTestId('login-register-link')).toBeVisible());
    expect(router.getPathname()).toBe('/login');
    expect(router.getSearchParams()).toEqual({ return_to: '/participant-invitations' });
    expect(router.getPathnameWithParams()).not.toContain('good-token');
    expect(screen.queryByTestId('register-email')).toBeNull();
    expect(screen.queryByTestId('register-invitation-notice')).toBeNull();

    const calls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(calls.filter((url) => url.includes('good-token'))).toEqual([]);
    expect(calls.filter((url) => url.includes('/participant-invitations'))).toEqual([]);
  });
});
