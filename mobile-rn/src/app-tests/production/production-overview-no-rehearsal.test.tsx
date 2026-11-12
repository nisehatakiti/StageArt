import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * See production-overview-full.test.tsx's docblock. Kept in its own
 * file, matching the established Expo Router testing-library cross-test
 * renderRouter() query-cache leak precedent (home-dashboard-error-productions-ok.test.tsx,
 * participating-productions-*.test.tsx).
 */
describe('公演概要ダッシュボード', () => {
  it('shows the empty state, not an error, when this Production has no upcoming Rehearsal', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/overview'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: { upcoming_rehearsals: [], notifications: [], followed_organizations_feed: [] } },
      { test: (u) => u.endsWith('/me/participating-productions'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/overview' });

    await waitFor(() => expect(screen.getByTestId('production-overview-no-upcoming-rehearsal')).toBeVisible());
    expect(screen.queryByTestId('production-overview-upcoming-rehearsals')).toBeNull();
    expect(screen.queryByTestId('production-overview-my-participation')).toBeNull();
  });
});
