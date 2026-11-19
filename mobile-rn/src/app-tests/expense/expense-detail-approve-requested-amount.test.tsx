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


describe('Expense detail: approving the requested amount', () => {
  it('omits approved_amount when the approver keeps the requested total', async () => {
    jest.spyOn(Alert, 'alert').mockImplementation((_title, _message, buttons) => {
      buttons?.find((button) => button.style === 'default')?.onPress?.();
    });

    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: accountingManagerProduction },
      { test: (u) => u.endsWith('/expenses/exp-1/approve'), status: 200, body: expenseFixture({ status: 'POSTED', approved_amount: 5000 }) },
      { test: (u) => u.endsWith('/expenses/exp-1'), status: 200, body: expenseFixture() },
    ]);

    renderRouter('src/app', { initialUrl: '/expenses/exp-1' });

    await waitFor(() => expect(screen.getByTestId('expense-approve')).toBeVisible());
    fireEvent.press(screen.getByTestId('expense-approve'));

    await waitFor(() => expect(patchCalls('/expenses/exp-1/approve')).toHaveLength(1));
    expect(patchCalls('/expenses/exp-1/approve')[0].body).toEqual({ revision: 1 });
  });
});
