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
 * StageArt Web版 団体管理 Phase: 参加申請管理. Fully backed by the real
 * GET /organizations/{id}/membership-requests + POST .../approve|reject
 * (MembershipRestController.php) - unlike members.tsx, this is not
 * limited by a Backend gap. Verifies the requester's name/status/date
 * from the real MembershipRequestResult shape, and that pressing 承認
 * fires the real approve endpoint.
 */
describe('Web 参加申請管理', () => {
  it('lists a pending request with requester/status/date and approving it calls the real approve endpoint', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
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
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/organizations/org-1/membership-requests' });

    await waitFor(() => expect(screen.getByTestId('membership-request-row-req-1')).toBeVisible());
    expect(screen.getByText('山田 太郎')).toBeVisible();
    expect(screen.getByText('申請中')).toBeVisible();

    fireEvent.press(screen.getByTestId('membership-request-approve-req-1'));

    await waitFor(() => {
      const approveCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, options]: [string, RequestInit]) => url.includes('/membership-requests/req-1/approve') && options?.method === 'POST'
      );
      expect(approveCall).toBeDefined();
    });
  });
});
