import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, orgOne, productionTwo, projectOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const accountingManager = { ...productionTwo, delegate_role: 'ACCOUNTING_MANAGER', delegate_roles: ['ACCOUNTING_MANAGER'] };

/** 会計 card: shown to an ACCOUNTING_MANAGER (the PrimaryManager case is web-production-management-top). */
describe('Web 公演管理トップ: 会計担当', () => {
  it('renders the 会計 card for an ACCOUNTING_MANAGER', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [orgOne] },
      { test: (u) => u.endsWith('/projects'), status: 200, body: [projectOne] },
      { test: (u) => u.endsWith(`/productions/${productionTwo.id}`), status: 200, body: accountingManager },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
    ]);

    renderRouter('src/app', { initialUrl: `/productions/${productionTwo.id}` });

    await waitFor(() => expect(screen.getByTestId('production-management-name')).toBeVisible());
    expect(screen.getByTestId('production-management-menu-accounting')).toBeVisible();
    expect(screen.queryByTestId('production-management-menu-publish')).toBeNull();
  });
});
