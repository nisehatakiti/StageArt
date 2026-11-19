import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingManagerProduction, expenseFixture, mockFetchRoutes, patchCalls } from './__fixtures__/expenseFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));


/**
 * An ACCOUNTING_MANAGER's own submitted Expense: no 承認 / 却下 (self-
 * approval is forbidden - the API refuses it too), only 取り下げ.
 */
describe('Expense detail: an approver viewing their own Expense', () => {
  it('offers withdrawal but not approval', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: accountingManagerProduction },
      { test: (u) => u.endsWith('/expenses/exp-1/withdraw'), status: 200, body: expenseFixture({ status: 'DRAFT', created_by: 'person-1', submitted_by: 'person-1' }) },
      { test: (u) => u.endsWith('/expenses/exp-1'), status: 200, body: expenseFixture({ created_by: 'person-1', submitted_by: 'person-1' }) },
    ]);

    renderRouter('src/app', { initialUrl: '/expenses/exp-1' });

    await waitFor(() => expect(screen.getByTestId('expense-withdraw')).toBeVisible());
    expect(screen.queryByTestId('expense-review-actions')).toBeNull();

    fireEvent.press(screen.getByTestId('expense-withdraw'));

    await waitFor(() => expect(patchCalls('/expenses/exp-1/withdraw')).toHaveLength(1));
    await waitFor(() => expect(screen.getByTestId('expense-status-value')).toHaveTextContent('下書き'));
    expect(screen.getByTestId('expense-submit')).toBeVisible();
  });
});
