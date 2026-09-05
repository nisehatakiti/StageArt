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
 * StageArt Blueprint再構成 Phase 1c §4: Home no longer has an
 * Organization Switcher/picker - every Organization the Person belongs
 * to renders as its own card, unconditionally, with no "current
 * Organization" selection state to switch between. This replaces the
 * previous "Home: multiple Organization memberships" test (which
 * asserted on the now-removed `organization-tile-*`/picker rendering).
 */
describe('Home: multiple Organization memberships', () => {
  it('shows every Organization as its own card with correct Organization-Scope role labels, no switcher', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne, orgTwo] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne, projectTwo] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId(`home-organization-row-${orgOne.id}`)).toBeVisible());
    expect(screen.getByTestId(`home-organization-row-${orgTwo.id}`)).toBeVisible();
    expect(screen.getByText('○○演劇団')).toBeVisible();
    expect(screen.getByText('△△企画')).toBeVisible();
    // Organization Scope roles (OWNER/MEMBER via Membership), not
    // Production Scope's PrimaryManager/Delegate.
    expect(screen.getByText('オーナー')).toBeVisible();
    expect(screen.getByText('メンバー')).toBeVisible();

    // No switcher/picker of any kind.
    expect(screen.queryByTestId('organization-switcher')).toBeNull();
    expect(screen.queryByTestId('organization-picker')).toBeNull();
  });
});
