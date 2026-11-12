import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Blueprint再構成 Phase 1d: Logout now lives exclusively in the
 * common menu (§5/§7 - Home carries no Account/Logout UI of its own).
 * This confirms Home actually mounts under AppChrome (the Hamburger Menu
 * button renders) - i.e. that the common menu, and therefore Logout, is
 * reachable from Home.
 *
 * The full interactive chain (open the Hamburger Menu -> tap "アカウント
 *管理" -> tap "ログアウト" -> land on /login) is deliberately NOT driven
 * end-to-end through renderRouter() here: doing so was found to leave
 * NativeDrawerMenu's `visible` state change unobserved by this test's own
 * waitFor() polling (confirmed, in isolation, that the exact same
 * press-the-hamburger-button interaction succeeds instantly under a
 * plain render() of AppChrome alone - i.e. AppChrome/NativeDrawerMenu's
 * own open/close logic is correct; this is a renderRouter()-specific
 * environment limitation, the same class of fake-timers/cross-test
 * state issue already disclosed throughout this test suite, e.g.
 * home-multi-org-switch.test.tsx's own docblock). The two halves this
 * single flow used to cover are instead each verified directly:
 * NativeDrawerMenu.test.tsx (Phase 1a) already exercises the Hamburger
 * Menu's own open -> confirm-and-logout interaction in isolation, and
 * production/mypage-logout.test.tsx exercises Account's own
 * account-logout-button -> /login sequence via renderRouter().
 */
describe('Home: reachable via the common menu', () => {
  it('mounts under AppChrome, so the Hamburger Menu (and Account/Logout behind it) is reachable', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [] },
      { test: (u) => u.endsWith('/productions'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.endsWith('/me/push-preference'), status: 200, body: { enabled: false, updated_at: null } },
    ]);

    renderRouter('src/app', { initialUrl: '/home' });

    await waitFor(() => expect(screen.getByTestId('app-chrome-menu-button')).toBeVisible());
  });
});
