import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingManagerProduction, expenseFixture, mockFetchRoutes } from './__fixtures__/expenseFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));


describe('Expense detail: a multi-line Expense', () => {
  it('offers approval of the requested total only', async () => {
    const lines = [
      { id: 'line-1', account_id: 'acc-1', amount: 3000, description: '小道具' },
      { id: 'line-2', account_id: 'acc-1', amount: 2000, description: '文具' },
    ];
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: accountingManagerProduction },
      { test: (u) => u.endsWith('/expenses/exp-1'), status: 200, body: expenseFixture({ lines }) },
    ]);

    renderRouter('src/app', { initialUrl: '/expenses/exp-1' });

    await waitFor(() => expect(screen.getByTestId('expense-approved-amount-fixed')).toHaveTextContent('5,000円'));
    expect(screen.queryByTestId('expense-approved-amount-input')).toBeNull();
    expect(screen.getByTestId('expense-line-line-1')).toHaveTextContent(/3,000円/);
    expect(screen.getByTestId('expense-line-line-2')).toHaveTextContent(/2,000円/);
  });
});
