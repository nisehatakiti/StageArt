import { renderRouter, screen, waitFor, within } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, productionOne, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * StageArt Web版 公演管理 Phase: 公演管理トップ (`/productions/[id]`) - the
 * route WebLayout's own submenu already pointed to. `productionOne` here
 * is `is_primary_manager: true` (its own fixture default), so every
 * remaining management card should be enabled.
 *
 * StageArt UI再構成 instruction (this round): this screen no longer
 * duplicates items already reachable from the Production Context sidebar
 * (公演情報/メンバー管理/稽古管理/etc. - see useNavMenu.ts) - it now only
 * keeps the 4 items with no sidebar entry at all (公開設定/メンバー実績
 * サマリー/会計/通知), plus a Dashboard-style 概要 section built from
 * fields already on the fetched Production.
 */
describe('Web 公演管理トップ', () => {
  it('shows the Production name/status/概要 and the non-sidebar-duplicate cards enabled for its PrimaryManager', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1' });

    await waitFor(() => expect(screen.getByTestId('production-management-name')).toBeVisible());
    expect(screen.getByTestId('production-management-name').props.children).toBe('○○公演2026');
    expect(within(screen.getByTestId('production-status-pill')).getByText('未公開')).toBeVisible();

    // The 9 items already reachable from the Production Context sidebar
    // are no longer duplicated as cards on this screen.
    expect(screen.queryByTestId('production-management-menu-edit')).toBeNull();
    expect(screen.queryByTestId('production-management-menu-participants')).toBeNull();
    expect(screen.queryByTestId('production-management-menu-schedule')).toBeNull();

    // The 4 items with no sidebar entry remain here.
    expect(screen.getByTestId('production-management-menu-publish').props.accessibilityState?.disabled).toBeFalsy();
    expect(screen.getByTestId('production-management-menu-member-performance-summary')).toBeVisible();
    expect(screen.getByTestId('production-management-menu-accounting')).toBeVisible();
    expect(screen.getByTestId('production-management-menu-notifications')).toBeVisible();

    // Unpublished: no 公開ページを見る link yet.
    expect(screen.queryByTestId('production-management-view-public')).toBeNull();
  });
});
