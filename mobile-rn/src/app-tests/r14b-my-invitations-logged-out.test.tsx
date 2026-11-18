import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async () => null),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * PR #7 R14-B stage 9 (design doc §10 / §14.2): a logged-out visitor
 * opening the invitation mail's link `/participant-invitations` is sent to
 * login with return_to=/participant-invitations; nothing about any
 * invitation is requested or shown before login.
 */
describe('R14-B 招待一覧: 未ログイン', () => {
  it('goes to login with return_to and shows nothing of the invitations', async () => {
    mockFetchRoutes([]);

    const router = renderRouter('src/app', { initialUrl: '/participant-invitations' });

    await waitFor(() => expect(screen.getByTestId('login-register-link')).toBeVisible());
    expect(router.getPathname()).toBe('/login');
    expect(router.getSearchParams()).toEqual({ return_to: '/participant-invitations' });
    expect(screen.queryByTestId('participant-invitations-list')).toBeNull();
    expect(screen.queryByTestId('participant-invitations-empty')).toBeNull();

    const calls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(calls.filter((url) => url.includes('participant-invitations'))).toEqual([]);
  });
});
