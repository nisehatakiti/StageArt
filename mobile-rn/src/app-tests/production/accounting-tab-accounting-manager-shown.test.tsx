import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingFull, mockFetchRoutes, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const accountingManager = { ...productionOne, is_primary_manager: false, delegate_role: 'ACCOUNTING_MANAGER', delegate_roles: ['ACCOUNTING_MANAGER'] };

/** An ACCOUNTING_MANAGER gets the 会計 tab and the figures the server returns. */
describe('Production Shell: 会計 tab for an ACCOUNTING_MANAGER', () => {
  it('lists the 会計 tab and shows the Accounting Summary', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: accountingManager },
      { test: (u) => u.includes('/productions/prod-1/accounting'), status: 200, body: accountingFull },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/accounting' });

    await waitFor(() => expect(screen.getByTestId('accounting-budget-row')).toBeVisible());
    expect(screen.getByTestId('accounting-budget-row-value')).toHaveTextContent('¥300,000');
    expect(screen.getAllByText('会計').length).toBeGreaterThan(0);
  });
});
