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
 * StageArt Blueprint再構成 Phase 1c §4: no more Organization Switcher or
 * auto-select state - a single Organization membership simply renders
 * as the one card there is, with its Productions inline. Kept in its
 * own file - see Phase 5.1's documented Expo Router testing-library
 * cross-test navigation state leak (multiple renderRouter() calls in
 * one file).
 */
describe('所属団体: single Organization membership', () => {
  it('shows the Organization as its one row (no picker/switcher) with its Productions counted', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [productionOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    // R7: 3113937 (2026-10-06) moved the Organization list, with each
    // Organization's Productions, off Home to the 「所属団体」 menu entry
    // (/organizations). That list shows each Organization's Production
    // count; the Production names are on its 公演・活動一覧, covered by
    // web-organization-productions.test.tsx (one renderRouter() per test).
    renderRouter('src/app', { initialUrl: '/organizations' });

    await waitFor(() => expect(screen.getByTestId(`organizations-row-${orgOne.id}`)).toBeVisible());
    expect(screen.queryByTestId('organization-picker')).toBeNull();
    expect(screen.queryByTestId('organization-switcher')).toBeNull();
    expect(screen.getByText('○○演劇団')).toBeVisible();
    expect(screen.getByText('オーナー')).toBeVisible();
    await waitFor(() => expect(screen.getByText('公演 1件')).toBeVisible());
  });
});
