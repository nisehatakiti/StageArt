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
 *
 * StageArt Web検証 STEP4 (2026-09-05): PrimaryNavGrid no longer includes
 * 団体を探す/公演・活動を探す/参加している公演・活動/プロフィール - see
 * home.tsx's own top docblock (docs/12-FunctionalStructure.md §15.1
 * alignment: those four are already reachable via the confirmed Menu or
 * ProfileContent.tsx, so Home's own body no longer duplicates them).
 *
 * StageArt Home仕様追加 (2026-09-05): the grid itself (and 観劇履歴,
 * which no longer appears there at all - no Backend data source exists
 * to confirm it) is covered by dedicated tests now
 * (home-nav-conditional.test.tsx) - this test keeps its own original
 * purpose (no Organization-management section for an unaffiliated
 * Person) and simply confirms this account's home-quick-action-
 * create-organization (always present) renders, using the shared
 * default /me/favorites: [] fixture (see homeFixtures.ts).
 */
describe('Home: no Organization memberships', () => {
  it('shows the Person Home, with no Organization-management section at all', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('home-quick-action-create-organization')).toBeVisible());
    expect(screen.queryByTestId('home-primary-nav')).toBeNull();
    expect(screen.queryByTestId('home-nav-viewing-history')).toBeNull();
    expect(screen.queryByTestId('home-nav-favorites')).toBeNull();
    expect(screen.queryByTestId('home-nav-discover-organizations')).toBeNull();
    expect(screen.queryByTestId('home-nav-discover-productions')).toBeNull();
    expect(screen.queryByTestId('home-nav-participating-productions')).toBeNull();
    expect(screen.queryByTestId('home-nav-profile')).toBeNull();
    expect(screen.queryByTestId('home-quick-action-join')).toBeNull();

    expect(screen.queryByText('所属しているOrganizationがありません。')).toBeNull();
    expect(screen.queryByTestId('organization-picker')).toBeNull();
    expect(screen.queryByTestId('organization-switcher')).toBeNull();
    expect(screen.queryByTestId('production-list')).toBeNull();
  });
});
