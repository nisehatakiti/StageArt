import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingManagerProduction, expenseFixture, mockFetchRoutes, patchCalls } from './__fixtures__/expenseFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('Expense detail: rejecting without a reason', () => {
  it('keeps 却下 unavailable while the reason is blank and sends nothing', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: accountingManagerProduction },
      { test: (u) => u.endsWith('/expenses/exp-1'), status: 200, body: expenseFixture() },
    ]);

    renderRouter('src/app', { initialUrl: '/expenses/exp-1' });

    await waitFor(() => expect(screen.getByTestId('expense-reject')).toBeVisible());
    expect(screen.getByTestId('expense-reject')).toBeDisabled();

    fireEvent.changeText(screen.getByTestId('expense-reject-reason-input'), '   ');
    await waitFor(() => expect(screen.getByTestId('expense-reject-reason-input').props.value).toBe('   '));
    expect(screen.getByTestId('expense-reject')).toBeDisabled();

    fireEvent.press(screen.getByTestId('expense-reject'));

    expect(patchCalls('/expenses/exp-1/reject')).toHaveLength(0);
  });
});
