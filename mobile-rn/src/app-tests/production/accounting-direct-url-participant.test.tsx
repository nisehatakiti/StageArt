import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingFull, mockFetchRoutes, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * A plain participant cannot read the Production (403), so the screen
 * stays refused - it does not fall back to showing figures - and the
 * Accounting Summary is never requested.
 */
describe('Accounting screen: direct URL by a plain participant', () => {
  it('shows the refusal when the Production itself is refused', async () => {
    mockFetchRoutes([
      {
        test: (u) => u.endsWith('/productions/prod-1'),
        status: 403,
        body: { code: 'stageart_production_access_denied', message: 'Forbidden' },
      },
      { test: (u) => u.includes('/productions/prod-1/accounting'), status: 200, body: accountingFull },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/accounting' });

    await waitFor(() => expect(screen.getByTestId('accounting-forbidden')).toBeVisible());
    expect(screen.queryByTestId('accounting-budget-row')).toBeNull();
    expect(screen.queryByText('会計')).toBeNull();
    const calls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(calls.some((url) => url.includes('/productions/prod-1/accounting'))).toBe(false);
  });
});
