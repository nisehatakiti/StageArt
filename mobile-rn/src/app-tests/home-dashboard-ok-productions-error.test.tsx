import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * §21 of this Phase's instruction: Dashboard and Production Navigator
 * are independent Failure Domains. Kept in its own file - see
 * home-dashboard-error-productions-ok.test.tsx's docblock for why this
 * pairing is split rather than co-located in one file.
 */
describe('Home: Production Navigator fails, Dashboard does not', () => {
  it('Production list failing still shows the Personal Overview normally', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions'), status: 403, body: { code: 'stageart_forbidden', message: 'Forbidden' } },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('productions-error')).toBeVisible());
    // The unrelated Personal Overview section must render normally
    // regardless (i.e. not itself in an error/loading state) - both
    // lists are empty here, so per docs/04-HomeRoleBasedMenu.md it
    // renders nothing, which is the "normal" outcome being asserted.
    expect(screen.queryByTestId('dashboard-loading')).toBeNull();
    expect(screen.queryByTestId('dashboard-error')).toBeNull();
  });
});
