import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingFull, mockFetchRoutes, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const ticketManager = { ...productionOne, is_primary_manager: false, delegate_role: 'TICKET_MANAGER', delegate_roles: ['TICKET_MANAGER'] };

/**
 * Opening /production/{id}/accounting directly (URL / deep link) as a
 * delegate without ACCOUNTING_MANAGER: the screen refuses on its own and
 * never requests the Accounting Summary. The server answer is mocked as
 * the full figures on purpose - they must still not appear.
 */
describe('Accounting screen: direct URL by a delegate without ACCOUNTING_MANAGER', () => {
  it('shows the refusal and requests no accounting figure', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: ticketManager },
      { test: (u) => u.includes('/productions/prod-1/accounting'), status: 200, body: accountingFull },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/accounting' });

    await waitFor(() => expect(screen.getByTestId('accounting-forbidden')).toBeVisible());
    expect(screen.getByText('この情報を表示する権限がありません。')).toBeVisible();
    expect(screen.queryByTestId('accounting-budget-row')).toBeNull();
    expect(screen.queryByText('¥300,000')).toBeNull();
    const calls = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url));
    expect(calls.some((url) => url.includes('/productions/prod-1/accounting'))).toBe(false);
  });
});
