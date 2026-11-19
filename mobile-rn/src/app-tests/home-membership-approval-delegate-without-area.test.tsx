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
 * Neither a delegate without メンバー管理 nor a plain member gets the
 * 参加申請 section on Home: the requests are not even requested.
 */
describe('Home: 参加申請 (メンバー管理を持たない代理人・一般メンバー)', () => {
  it('shows no request section and never requests the pending list', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/organizations'),
        status: 200,
        body: [
          { ...orgOne, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['PRODUCTIONS', 'ACCOUNTING'] },
          { ...orgOne, id: 'org-2', name: '別の劇団', current_person_role: 'MEMBER', current_person_management_areas: [] },
        ],
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.endsWith('/me/notifications'), status: 200, body: [] },
      { test: (u) => u.endsWith('/membership-requests'), status: 200, body: [{ id: 'req-1', organization_id: 'org-1', person_id: 'person-9', person_family_name: '山田', person_given_name: '太郎', status: 'REQUESTED', requested_at: '2026-08-20T10:00:00+09:00', joined_at: null }] },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.queryByTestId('dashboard-loading')).toBeNull());
    expect(screen.queryByTestId('home-membership-approval')).toBeNull();
    const urls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(urls.some((url) => url.endsWith('/organizations'))).toBe(true);
    expect(urls.some((url) => url.endsWith('/membership-requests'))).toBe(false);
  });
});
