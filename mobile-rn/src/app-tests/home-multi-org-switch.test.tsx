import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, orgTwo, projectOne, projectTwo } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * §6: kept in its own file - see Phase 5.1's documented Expo Router
 * testing-library cross-test navigation state leak.
 *
 * Scoped to what is reliably verifiable through Expo Router's
 * renderRouter() (see the Phase 5.2 report's Test Results section): the
 * picker's own rendering for multiple Organization memberships. The
 * subsequent tap-to-select/switch interaction is verified instead
 * against OrganizationContext directly via renderHook in
 * OrganizationContext.test.tsx - pressing an item inside this specific
 * 2-item picker, rendered under renderRouter()'s forced Jest fake
 * timers, was empirically found to silently drop the resulting React
 * state update, reproduced down to a bare useState counter with no
 * Organization Context or navigation involved at all. This is a
 * disclosed Jest/RNTL/fake-timers environment limitation for this one
 * render shape, not an application defect - the same
 * press-a-TouchableOpacity-and-observe-a-state-update pattern works
 * correctly everywhere else in this test suite (login, logout-driven
 * redirect examined during the isolation trail, Home -> Production
 * navigation).
 */
describe('Home: multiple Organization memberships', () => {
  it('shows the Organization picker with correct names and Organization-Scope role labels', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne, orgTwo] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne, projectTwo] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('organization-tile-org-1')).toBeVisible());
    expect(screen.getByTestId('organization-tile-org-2')).toBeVisible();
    expect(screen.getByText('○○演劇団')).toBeVisible();
    expect(screen.getByText('△△企画')).toBeVisible();
    // Organization Scope roles (OWNER/MEMBER via Membership), not
    // Production Scope's PrimaryManager/Delegate - see organization-tile.tsx.
    expect(screen.getByText('所属：オーナー')).toBeVisible();
    expect(screen.getByText('所属：メンバー')).toBeVisible();

    // No auto-select for >1 Organization: the Production section must
    // not render yet.
    expect(screen.queryByTestId('production-list')).toBeNull();
  });
});
