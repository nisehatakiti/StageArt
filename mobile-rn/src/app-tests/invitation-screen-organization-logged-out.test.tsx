import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Invitation policy 2026-11-27: a logged-out visitor opening an organization
 * invitation mail's link logs in first and comes back to that invitation
 * (return_to); nothing about it is requested before login.
 */
describe('招待の画面（団体）: 未ログイン', () => {
  it('goes to login with the invitation as return_to', async () => {
    mockFetchRoutes([]);

    const router = renderRouter('src/app', { initialUrl: '/organization-invitations/org-invitation-1' });

    await waitFor(() => expect(screen.getByTestId('login-register-link')).toBeVisible());
    expect(router.getPathname()).toBe('/login');
    expect(router.getSearchParams()).toEqual({ return_to: '/organization-invitations/org-invitation-1' });
    const calls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(calls.filter((url) => url.includes('invitations'))).toEqual([]);
  });
});
