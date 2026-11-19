import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, productionTwo, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * メニューの非表示統一: someone who is not the PrimaryManager (here a
 * REHEARSAL_MANAGER delegate) does not get the 公開設定 / メンバー実績
 * サマリー cards at all - they are not shown greyed out. 通知, which every
 * Production member uses, stays. 会計 is for the PrimaryManager or an
 * ACCOUNTING_MANAGER only, so a REHEARSAL_MANAGER does not get it either.
 */
describe('Web 公演管理トップ: 主担当者以外', () => {
  it('does not render the PrimaryManager-only cards and keeps the member cards', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith(`/productions/${productionTwo.id}`), status: 200, body: productionTwo },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/productions/${productionTwo.id}` });

    await waitFor(() => expect(screen.getByTestId('production-management-name')).toBeVisible());
    expect(screen.getByTestId('production-management-menu')).toBeVisible();
    expect(screen.queryByTestId('production-management-menu-publish')).toBeNull();
    expect(screen.queryByTestId('production-management-menu-member-performance-summary')).toBeNull();
    expect(screen.queryByTestId('production-management-menu-accounting')).toBeNull();
    expect(screen.getByTestId('production-management-menu-notifications')).toBeVisible();
    expect(screen.queryByTestId('production-management-lifecycle-actions')).toBeNull();
  });
});
