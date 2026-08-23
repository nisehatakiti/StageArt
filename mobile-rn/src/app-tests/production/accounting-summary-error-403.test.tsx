import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockFetchRoutes, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('Accounting tab: fails to load with 403', () => {
  it('shows an Authorization-specific error message and a Retry control', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      {
        test: (u) => u.includes('/productions/prod-1/accounting'),
        status: 403,
        body: { code: 'stageart_production_accounting_access_denied', message: 'Forbidden' },
      },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/accounting' });

    await waitFor(() => expect(screen.getByTestId('accounting-error')).toBeVisible());
    expect(screen.getByText('この情報を表示する権限がありません。')).toBeVisible();
    expect(screen.getByTestId('accounting-retry')).toBeVisible();
  });
});
