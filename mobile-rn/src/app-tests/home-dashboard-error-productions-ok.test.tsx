import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * §21 of this Phase's instruction: Dashboard and Production Navigator
 * are independent Failure Domains. Kept in its own file - see the other
 * Home test files' documented Expo Router testing-library cross-test
 * renderRouter() state leak (this specific pairing was empirically found
 * to fail only when run alongside its counterpart in one file, passing
 * in isolation - the same class of environment limitation, not an
 * application defect).
 */
describe('Home: Dashboard fails, Production Navigator does not', () => {
  it('Dashboard failing still shows the Production list normally', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [productionOne] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 403, body: { code: 'stageart_forbidden', message: 'Forbidden' } },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('dashboard-error')).toBeVisible());
    expect(screen.getByTestId('dashboard-retry')).toBeVisible();
    // The unrelated 公演 section must render normally regardless.
    await waitFor(() => expect(screen.getByTestId('production-list')).toBeVisible());
    expect(screen.getByText('○○公演2026')).toBeVisible();
  });
});
