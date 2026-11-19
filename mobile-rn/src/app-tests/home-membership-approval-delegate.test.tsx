import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * 参加申請 is the メンバー管理 area on the server: a delegate holding it
 * sees the Organization's pending requests on Home, like the Owner, and
 * approving one calls the real approve endpoint.
 */
describe('Home: 参加申請 (メンバー管理を持つ代理人)', () => {
  it('shows the pending request and approving it calls the approve endpoint', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/organizations'),
        status: 200,
        body: [{ ...orgOne, current_person_role: 'ORGANIZATION_MANAGER', current_person_management_areas: ['MEMBERS'] }],
      },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.endsWith('/me/notifications'), status: 200, body: [] },
      {
        test: (u) => u.endsWith('/organizations/org-1/membership-requests'),
        status: 200,
        body: [
          {
            id: 'req-1',
            organization_id: 'org-1',
            person_id: 'person-9',
            person_family_name: '山田',
            person_given_name: '太郎',
            status: 'REQUESTED',
            requested_at: '2026-08-20T10:00:00+09:00',
            joined_at: null,
          },
        ],
      },
      { test: (u) => u.endsWith('/membership-requests/req-1/approve'), status: 200, body: {} },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('home-membership-approval')).toBeVisible());
    expect(screen.getByText('山田 太郎')).toBeVisible();

    fireEvent.press(screen.getByTestId('home-membership-approve-req-1'));

    await waitFor(() => {
      const approveCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, options]: [string, RequestInit]) => String(url).includes('/membership-requests/req-1/approve') && options?.method === 'POST'
      );
      expect(approveCall).toBeDefined();
    });
  });
});
