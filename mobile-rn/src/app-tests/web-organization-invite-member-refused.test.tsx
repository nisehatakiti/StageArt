import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * A plain member opening the 参加コード URL directly gets no controls, and
 * the Join Keys are not requested.
 */
describe('Web 参加コード: 一般メンバー', () => {
  it('is refused and the Join Keys are not requested', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ ...orgOne, current_person_role: 'MEMBER', current_person_management_areas: [] }] },
      { test: (u) => u.endsWith('/organizations/org-1/join-keys'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/invite` });

    await waitFor(() => expect(screen.getByTestId('organization-invite-unavailable')).toBeVisible());
    expect(screen.queryByTestId('organization-join-key-issue')).toBeNull();
    const urls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(urls.some((url) => url.endsWith('/join-keys'))).toBe(false);
  });
});
