import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingFull, mockFetchRoutes, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** §5/§6/§7/§9/§10: real Backend-shaped data renders as real values, not
 * "未実装" - the entire point of Phase 5.5 connecting the placeholder. */
describe('Accounting tab: full Budget + Actual + Variance from the real API', () => {
  it('renders 予算/実績/差異 exactly as the server computed them', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.includes('/productions/prod-1/accounting'), status: 200, body: accountingFull },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/accounting' });

    await waitFor(() => expect(screen.getByTestId('accounting-budget-row')).toBeVisible());

    expect(screen.getByTestId('accounting-budget-row-value')).toHaveTextContent('¥300,000');
    expect(screen.getByTestId('accounting-actual-row-value')).toHaveTextContent('-¥180,000');
    expect(screen.getByTestId('accounting-variance-row-value')).toHaveTextContent('-¥480,000');

    expect(screen.queryByText('未実装')).toBeNull();
  });
});
