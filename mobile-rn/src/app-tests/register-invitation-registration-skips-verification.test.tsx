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
 * PR #7 R14-B (design doc §9.2, confirmed by the user; rewritten for the
 * spec change - previously a registration from an invitation link
 * forwarded the token and skipped the mail confirmation, and in the first
 * stage-9 version the link stayed on the registration form): an old
 * invitation link opened while logged in goes to the invitation list
 * (/participant-invitations). The token is not read, sent or carried into
 * the URL, and nothing is registered.
 */
describe('register screen: old invitation link while logged in', () => {
  it('goes to the invitation list without the token', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/me/participant-invitations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    const router = renderRouter('src/app', { initialUrl: '/register?token=good-token' });

    await waitFor(() => expect(screen.getByTestId('participant-invitations-empty')).toBeVisible());
    expect(router.getPathname()).toBe('/participant-invitations');
    expect(router.getSearchParams()).toEqual({});

    const calls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(calls.filter((url) => url.includes('good-token'))).toEqual([]);
    expect(calls.filter((url) => url.endsWith('/auth/email/register'))).toEqual([]);
  });
});
