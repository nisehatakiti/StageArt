import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, myDashboardEmpty, productionTwo } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * ProductionDelegate実用化 instruction §10/§11: canManageProductionDelegates()
 * is PrimaryManager-only on the Backend - unlike Questionnaire/Performance/
 * etc., no delegate_role grants access here. productionTwo already fixtures
 * is_primary_manager: false with a real delegate_role (REHEARSAL_MANAGER)
 * set, confirming this screen denies even an active delegate, not just an
 * outsider.
 */
describe('Web 担当者: 権限', () => {
  it('denies access to a non-PrimaryManager, even one holding another delegate role', async () => {
    mockFetchRoutes([{ test: (u) => u.endsWith('/productions/prod-2'), status: 200, body: productionTwo }, { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty }]);

    renderRouter('src/app', { initialUrl: '/productions/prod-2/delegates' });

    await waitFor(() => expect(screen.getByTestId('production-delegates-forbidden')).toBeVisible());
  });
});
