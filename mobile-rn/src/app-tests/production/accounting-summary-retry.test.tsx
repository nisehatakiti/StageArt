import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingFull, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('Accounting tab: Retry after a failed load', () => {
  it('refetches and shows real data after tapping 再読み込み', async () => {
    let accountingCallCount = 0;

    global.fetch = jest.fn(async (input: unknown) => {
      const url = String(input);

      if (url.endsWith('/auth/refresh')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify({ access_token: 'refreshed-token', token_type: 'Bearer', expires_in: 3600 }),
        } as Response;
      }

      if (url.endsWith('/productions/prod-1')) {
        return { ok: true, status: 200, text: async () => JSON.stringify(productionOne), json: async () => productionOne } as Response;
      }

      if (url.includes('/productions/prod-1/accounting')) {
        accountingCallCount += 1;

        if (accountingCallCount === 1) {
          const body = { code: 'stageart_production_accounting_access_denied', message: 'Forbidden' };
          return { ok: false, status: 403, text: async () => JSON.stringify(body), json: async () => body } as Response;
        }

        return { ok: true, status: 200, text: async () => JSON.stringify(accountingFull), json: async () => accountingFull } as Response;
      }

      throw new Error(`Unmocked fetch: ${url}`);
    });

    renderRouter('src/app', { initialUrl: '/production/prod-1/accounting' });

    await waitFor(() => expect(screen.getByTestId('accounting-error')).toBeVisible());

    fireEvent.press(screen.getByTestId('accounting-retry'));

    await waitFor(() => expect(screen.getByTestId('accounting-budget-row-value')).toHaveTextContent('¥300,000'));
    expect(accountingCallCount).toBe(2);
  });
});
