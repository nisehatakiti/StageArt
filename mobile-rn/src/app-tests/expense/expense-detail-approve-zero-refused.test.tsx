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

describe('Expense detail: a zero approved amount', () => {
  it('is flagged as soon as it is typed, cannot be approved, and is never sent', async () => {
    const alert = jest.spyOn(Alert, 'alert').mockImplementation(() => undefined);
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: accountingManagerProduction },
      { test: (u) => u.endsWith('/expenses/exp-1'), status: 200, body: expenseFixture() },
    ]);

    renderRouter('src/app', { initialUrl: '/expenses/exp-1' });

    await waitFor(() => expect(screen.getByTestId('expense-approved-amount-input')).toBeVisible());
    expect(screen.queryByTestId('expense-amount-error')).toBeNull();

    fireEvent.changeText(screen.getByTestId('expense-approved-amount-input'), '0');

    await waitFor(() => expect(screen.getByTestId('expense-amount-error')).toHaveTextContent('承認額は1円以上の整数で入力してください。'));
    expect(screen.getByTestId('expense-approve')).toBeDisabled();

    fireEvent.press(screen.getByTestId('expense-approve'));

    expect(alert).not.toHaveBeenCalled();
    expect(patchCalls('/expenses/exp-1/approve')).toHaveLength(0);
  });
});
