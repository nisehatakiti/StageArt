import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt UI再構成 instruction (this round §「Organization Context」):
 * 団体管理トップ is now a real Dashboard - Role, the actual Production
 * list, and (Owner only) a pending-membership-request count - all reused
 * from data already fetched elsewhere in the app (useOrganizationProductions/
 * usePendingMembershipRequests), not a new API.
 */
describe('Web 団体管理トップ: Dashboard content', () => {
  it('shows the current Role, the Production list, and the pending membership request count for the Owner', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [productionOne] },
      { test: (u) => u.endsWith('/organizations/org-1/membership-requests'), status: 200, body: [{ id: 'req-1' }, { id: 'req-2' }] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/organizations/org-1' });

    await waitFor(() => expect(screen.getByTestId('organization-management-role')).toHaveTextContent('オーナー'));
    await waitFor(() => expect(screen.getByTestId(`organization-management-production-${productionOne.id}`)).toBeVisible());
    expect(screen.getByTestId(`organization-management-production-${productionOne.id}`)).toHaveTextContent(new RegExp(productionOne.name));

    await waitFor(() => expect(screen.getByTestId('organization-management-pending-requests')).toHaveTextContent(/2件/));
  });
});
