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
 * StageArt 団体管理UI整理 §5: "Organization Contextから「参加申請」を削除
 * する。参加申請はHomeに表示する。" - the former organizations/[id]/
 * membership-requests.tsx screen (and its own test,
 * web-organization-membership-requests.test.tsx) is removed; this test
 * carries its real coverage (requester name + approving calls the real
 * Backend endpoint) forward onto Home's own HomeMembershipApprovalSection,
 * fully backed by the same real GET /organizations/{id}/membership-
 * requests + POST .../approve (MembershipRestController.php) as before -
 * not a placeholder.
 */
describe('Home: 参加申請', () => {
  it('shows a pending request for an Organization the Person owns, and approving it calls the real approve endpoint', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
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
    expect(screen.getByTestId('home-membership-approve-req-1')).toBeVisible();
    expect(screen.getByTestId('home-membership-reject-req-1')).toBeVisible();

    fireEvent.press(screen.getByTestId('home-membership-approve-req-1'));

    await waitFor(() => {
      const approveCall = (global.fetch as jest.Mock).mock.calls.find(
        ([url, options]: [string, RequestInit]) => url.includes('/membership-requests/req-1/approve') && options?.method === 'POST'
      );
      expect(approveCall).toBeDefined();
    });
  });

  it('shows nothing when there are no pending requests', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.endsWith('/me/notifications'), status: 200, body: [] },
      { test: (u) => u.endsWith('/organizations/org-1/membership-requests'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.queryByTestId('dashboard-loading')).toBeNull());
    expect(screen.queryByTestId('home-membership-approval')).toBeNull();
  });
});
