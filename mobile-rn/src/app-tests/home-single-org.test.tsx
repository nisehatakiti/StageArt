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
describe('Home: single Organization membership', () => {
  it('shows the Organization card (no picker/switcher) with its Productions', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [productionOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId(`home-organization-productions-${orgOne.id}`)).toBeVisible());
    expect(screen.queryByTestId('organization-picker')).toBeNull();
    expect(screen.queryByTestId('organization-switcher')).toBeNull();
    expect(screen.getByText('○○演劇団')).toBeVisible();
    expect(screen.getByText('○○公演2026')).toBeVisible();
  });
});
