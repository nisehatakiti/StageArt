import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * Blueprint v1.5 alignment Phase: an unaffiliated Person (no
 * Organization Membership at all - the normal state right after
 * registration) is not an error or an edge case to apologize for -
 * BusinessFlowUXClarifications.md §02 explicitly retires the old
 * "所属しているOrganizationがありません" single-line Empty State. Home now
 * shows the full Person-first navigation grid regardless, and the
 * Organization-management section (switcher/picker/Production list)
 * simply does not render at all for this user - not even its own Empty
 * State - since §02.6 scopes management features to users who actually
 * have a Membership.
 */
describe('Home: no Organization memberships', () => {
  it('shows the Person Home navigation grid, with no Organization-management section at all', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('home-primary-nav')).toBeVisible());
    expect(screen.getByTestId('home-nav-discover-organizations')).toBeVisible();
    expect(screen.getByTestId('home-nav-discover-productions')).toBeVisible();
    expect(screen.getByTestId('home-nav-viewing-history')).toBeVisible();
    expect(screen.getByTestId('home-nav-participating-productions')).toBeVisible();
    expect(screen.getByTestId('home-nav-profile')).toBeVisible();

    expect(screen.queryByText('所属しているOrganizationがありません。')).toBeNull();
    expect(screen.queryByTestId('organization-picker')).toBeNull();
    expect(screen.queryByTestId('organization-switcher')).toBeNull();
    expect(screen.queryByTestId('production-list')).toBeNull();
  });
});
