import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** §20's required check: "予定 / 会計 / マイページ が存在すること" for
 * the Production Shell (Phase 5.0/5.1's Navigation Map). Starts already
 * "authenticated" (mocked SecureStore returns stored credentials) since
 * this Shell is only reachable post-login.
 *
 * StageArt Blueprint再構成 Phase 1b: every screen under app/(app)/ (this
 * one included, since it moved there per instruction 8) now sits behind
 * a real `status === 'authenticated'` gate (app/(app)/_layout.tsx) - a
 * bare `global.fetch = jest.fn()` with no configured response starves
 * AuthProvider's own boot-time POST /auth/refresh, which resolves to
 * `unauthenticated` and redirects to /login before this screen ever
 * renders. mockFetchRoutes() (the same fixture the sibling
 * production-shell-title-heading tests already use) supplies a working
 * refresh + /me response by default. */
describe('Production Shell', () => {
  it('shows all 3 fixed Blueprint tabs and none other', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne }]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/schedule' });

    await waitFor(() => expect(screen.getAllByText('予定').length).toBeGreaterThan(0));
    expect(screen.getAllByText('会計').length).toBeGreaterThan(0);
    expect(screen.getAllByText('マイページ').length).toBeGreaterThan(0);

    // "and none other": the fixed 3-tab set must not gain a 4th tab
    // (e.g. an "Organization"/"Production" tab - explicitly prohibited
    // by FrontendArchitecture.md, see Phase 5.0/5.1's Navigation Map).
    expect(screen.queryByText('団体')).toBeNull();
    expect(screen.queryByText('公演')).toBeNull();
  });
});
