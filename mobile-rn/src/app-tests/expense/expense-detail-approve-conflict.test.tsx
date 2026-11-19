import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';
import { Alert } from 'react-native';

import { accountingManagerProduction, expenseFixture, mockFetchRoutes } from './__fixtures__/expenseFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));


describe('Expense detail: the application changed after it was opened', () => {
  it('shows the conflict (409) instead of pretending it was approved', async () => {
    jest.spyOn(Alert, 'alert').mockImplementation((_title, _message, buttons) => {
      buttons?.find((button) => button.style === 'default')?.onPress?.();
    });

    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: accountingManagerProduction },
      {
        test: (u) => u.endsWith('/expenses/exp-1/approve'),
        status: 409,
        body: { code: 'stageart_expense_conflict', message: 'The Expense was changed after it was reviewed.' },
      },
      { test: (u) => u.endsWith('/expenses/exp-1'), status: 200, body: expenseFixture() },
    ]);

    renderRouter('src/app', { initialUrl: '/expenses/exp-1' });

    await waitFor(() => expect(screen.getByTestId('expense-approve')).toBeVisible());
    fireEvent.press(screen.getByTestId('expense-approve'));

    await waitFor(() => expect(screen.getByTestId('expense-action-error')).toHaveTextContent(/この申請は既に処理されたか、内容が変更されています。/));
    expect(screen.getByTestId('expense-status-value')).toHaveTextContent('申請中');
  });
});
