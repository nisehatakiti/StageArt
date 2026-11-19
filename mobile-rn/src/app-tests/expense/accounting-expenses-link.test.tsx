import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingFull } from '../production/__fixtures__/productionShellFixtures';

import { accountingManagerProduction, expenseFixture, mockFetchRoutes } from './__fixtures__/expenseFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/**
 * 修正8: the 会計 tab is the manager menu's entry to pending Expense
 * applications (ExpenseApprovalWorkflow.md "Approval Entry / Manager Review").
 */
describe('Accounting tab: link to pending Expense applications', () => {
  it('opens the 経費申請 list', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: accountingManagerProduction },
      { test: (u) => u.includes('/productions/prod-1/accounting'), status: 200, body: accountingFull },
      { test: (u) => u.includes('/productions/prod-1/expenses'), status: 200, body: [expenseFixture()] },
      { test: (u) => u.includes('/productions/prod-1/notifications'), status: 200, body: [] },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/accounting' });

    await waitFor(() => expect(screen.getByTestId('accounting-expenses-link')).toBeVisible());
    fireEvent.press(screen.getByTestId('accounting-expenses-link'));

    await waitFor(() => expect(screen.getByTestId('production-expense-row-exp-1')).toBeVisible());
  });
});
