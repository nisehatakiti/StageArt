import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';
import { Alert } from 'react-native';

import { accountingManagerProduction, expenseFixture, mockFetchRoutes, patchCalls } from './__fixtures__/expenseFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));


/**
 * 修正8: an ACCOUNTING_MANAGER opens a submitted single-line Expense,
 * sees the requested amount and the history, approves a reduced total,
 * and the approval carries the revision shown on screen.
 */
describe('Expense detail: approving a changed amount', () => {
  it('sends the reviewed revision and the approved amount, then shows both amounts', async () => {
    jest.spyOn(Alert, 'alert').mockImplementation((_title, _message, buttons) => {
      buttons?.find((button) => button.style === 'default')?.onPress?.();
    });

    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: accountingManagerProduction },
      { test: (u) => u.endsWith('/expenses/exp-1/approve'), status: 200, body: expenseFixture({ status: 'POSTED', approved_amount: 2500, approved_by: 'person-1' }) },
      { test: (u) => u.endsWith('/expenses/exp-1'), status: 200, body: expenseFixture() },
    ]);

    renderRouter('src/app', { initialUrl: '/expenses/exp-1' });

    await waitFor(() => expect(screen.getByTestId('expense-review-actions')).toBeVisible());
    expect(screen.getByTestId('expense-status-value')).toHaveTextContent('申請中');
    expect(screen.getByTestId('expense-requested-amount-value')).toHaveTextContent('5,000円');
    expect(screen.getByTestId('expense-history-0')).toHaveTextContent(/第1回 申請/);
    expect(screen.queryByTestId('expense-applicant-actions')).toBeNull();

    fireEvent.changeText(screen.getByTestId('expense-approved-amount-input'), '2500');
    await waitFor(() => expect(screen.getByTestId('expense-approved-amount-input').props.value).toBe('2500'));
    fireEvent.press(screen.getByTestId('expense-approve'));

    await waitFor(() => expect(patchCalls('/expenses/exp-1/approve')).toHaveLength(1));
    expect(patchCalls('/expenses/exp-1/approve')[0].body).toEqual({ revision: 1, approved_amount: 2500 });

    await waitFor(() => expect(screen.getByTestId('expense-approved-amount-value')).toHaveTextContent('2,500円'));
    expect(screen.getByTestId('expense-requested-amount-value')).toHaveTextContent('5,000円');
    expect(screen.getByTestId('expense-status-value')).toHaveTextContent('承認済み');
    expect(screen.queryByTestId('expense-review-actions')).toBeNull();
  });
});
