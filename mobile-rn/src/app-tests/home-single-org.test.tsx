import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** §5 "1 Organizationなら自動選択": kept in its own file - see Phase
 * 5.1's documented Expo Router testing-library cross-test navigation
 * state leak (multiple renderRouter() calls in one file). */
describe('Home: single Organization membership', () => {
  it('auto-selects the only Organization (no picker) and shows its Productions', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [productionOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('production-list')).toBeVisible());
    expect(screen.queryByTestId('organization-picker')).toBeNull();
    expect(screen.getByText('○○演劇団 ▼')).toBeVisible();
    expect(screen.getByText('○○公演2026')).toBeVisible();
  });
});
