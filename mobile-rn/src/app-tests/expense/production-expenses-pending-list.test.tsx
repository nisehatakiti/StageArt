import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingManagerProduction, expenseFixture, mockFetchRoutes } from './__fixtures__/expenseFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));


describe('経費申請 list: an ACCOUNTING_MANAGER', () => {
  it('lists the pending applications and opens one', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: accountingManagerProduction },
      { test: (u) => u.includes('/productions/prod-1/expenses'), status: 200, body: [expenseFixture()] },
      { test: (u) => u.endsWith('/expenses/exp-1'), status: 200, body: expenseFixture() },
    ]);

    renderRouter('src/app', { initialUrl: '/productions/prod-1/expenses' });

    await waitFor(() => expect(screen.getByTestId('production-expense-row-exp-1')).toBeVisible());
    expect(screen.getByTestId('production-expense-row-exp-1')).toHaveTextContent(/衣装代/);
    expect(screen.getByTestId('production-expense-row-exp-1')).toHaveTextContent(/5,000円/);

    const listCall = (global.fetch as jest.Mock).mock.calls.map(([url]) => String(url)).find((url) => url.includes('/productions/prod-1/expenses'));
    expect(listCall).toContain('status=SUBMITTED');

    fireEvent.press(screen.getByTestId('production-expense-row-exp-1'));
    await waitFor(() => expect(screen.getByTestId('expense-detail')).toBeVisible());
  });
});
