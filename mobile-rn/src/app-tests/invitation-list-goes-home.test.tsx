import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Invitation policy 2026-11-27: there is no invitation list and no Home card
 * of invitations to accept any more. The link of the mails sent before
 * (/participant-invitations) goes Home, and Home asks nothing about
 * invitations.
 */
describe('以前の招待メールのリンク（招待一覧）', () => {
  it('goes Home, which shows no invitation card', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty }]);

    const router = renderRouter('src/app', { initialUrl: '/participant-invitations' });

    await waitFor(() => expect(router.getPathname()).toBe('/home'));
    await waitFor(() => expect(screen.getByTestId('home-screen')).toBeVisible());
    expect(screen.queryByTestId('home-participant-invitations')).toBeNull();
    const calls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(calls.filter((url) => url.includes('participant-invitations'))).toEqual([]);
  });
});
