import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

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
 * management card should be enabled.
 */
describe('Web 公演管理トップ', () => {
  it('shows the Production name/status and every management card enabled for its PrimaryManager', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1' });

    await waitFor(() => expect(screen.getByTestId('production-management-name')).toBeVisible());
    // WebLayout's own sidebar submenu heading also shows the Production
    // name (see WebLayout.tsx's productionName prop) - scope to the page
    // title itself to avoid an ambiguous match.
    expect(screen.getByTestId('production-management-name').props.children).toBe('○○公演2026');
    expect(screen.getByText('下書き')).toBeVisible();

    expect(screen.getByTestId('production-management-menu-edit').props.accessibilityState?.disabled).toBeFalsy();
    expect(screen.getByTestId('production-management-menu-participants').props.accessibilityState?.disabled).toBeFalsy();
    expect(screen.getByTestId('production-management-menu-publish').props.accessibilityState?.disabled).toBeFalsy();
    expect(screen.getByTestId('production-management-menu-schedule')).toBeVisible();
    expect(screen.getByTestId('production-management-menu-accounting')).toBeVisible();
    expect(screen.getByTestId('production-management-menu-notifications')).toBeVisible();

    // Unpublished: no 公開ページを見る link yet.
    expect(screen.queryByTestId('production-management-view-public')).toBeNull();
  });
});
