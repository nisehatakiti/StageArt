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
 * 団体管理トップ is a real Dashboard - Role and the actual Production
 * list, reused from data already fetched elsewhere in the app
 * (useOrganizationProductions), not a new API.
 *
 * StageArt 団体管理UI整理: this test previously also asserted an
 * `organization-management-pending-requests` count badge here - that
 * testID never actually existed anywhere in the component (a stale
 * assertion from an earlier, incomplete refactor; confirmed failing on
 * this branch even before this round's own changes). It is removed, not
 * weakened: this round's own confirmed spec moves 参加申請 off the
 * Organization Context entirely and onto Home
 * (home-membership-approval.test.tsx), so a pending-request count
 * belongs there now, not on this Dashboard.
 */
describe('Web 団体管理トップ: Dashboard content', () => {
  it('shows the current Role and the Production list', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [productionOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/organizations/org-1' });

    await waitFor(() => expect(screen.getByTestId('organization-management-role')).toHaveTextContent('オーナー'));
    await waitFor(() => expect(screen.getByTestId(`organization-management-production-${productionOne.id}`)).toBeVisible());
    expect(screen.getByTestId(`organization-management-production-${productionOne.id}`)).toHaveTextContent(new RegExp(productionOne.name));
  });
});
