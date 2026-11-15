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
describe('Home: Dashboard fails, the rest of Home does not', () => {
  it('Dashboard failing still shows the other Home sections normally', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [productionOne] },
      { test: (u) => u.endsWith('/me/notifications'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 403, body: { code: 'stageart_forbidden', message: 'Forbidden' } },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('dashboard-error')).toBeVisible());
    expect(screen.getByTestId('dashboard-retry')).toBeVisible();
    // R7: 3113937 (2026-10-06) moved the 団体/公演 section off Home (to
    // /organizations). The failure domain this test checks is unchanged:
    // the Dashboard failing must not take the rest of Home with it - the
    // greeting (GET /me) still renders normally.
    await waitFor(() => expect(screen.getByTestId('home-greeting')).toBeVisible());
    expect(screen.getByText(/舞台さん/)).toBeVisible();
  });
});
