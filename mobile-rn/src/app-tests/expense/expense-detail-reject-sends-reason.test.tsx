import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingManagerProduction, expenseFixture, mockFetchRoutes, patchCalls } from './__fixtures__/expenseFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));


describe('Expense detail: rejecting with a reason', () => {
  it('sends the trimmed reason and shows it', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: accountingManagerProduction },
      {
        test: (u) => u.endsWith('/expenses/exp-1/reject'),
        status: 200,
        body: expenseFixture({ status: 'REJECTED', rejection_reason: '領収書を添付してください', rejected_by: 'person-1' }),
      },
      { test: (u) => u.endsWith('/expenses/exp-1'), status: 200, body: expenseFixture() },
    ]);

    renderRouter('src/app', { initialUrl: '/expenses/exp-1' });

    await waitFor(() => expect(screen.getByTestId('expense-reject')).toBeVisible());
    fireEvent.changeText(screen.getByTestId('expense-reject-reason-input'), ' 領収書を添付してください ');
    await waitFor(() => expect(screen.getByTestId('expense-reject-reason-input').props.value).toBe(' 領収書を添付してください '));
    fireEvent.press(screen.getByTestId('expense-reject'));

    await waitFor(() => expect(patchCalls('/expenses/exp-1/reject')).toHaveLength(1));
    expect(patchCalls('/expenses/exp-1/reject')[0].body).toEqual({ reason: '領収書を添付してください' });
    await waitFor(() => expect(screen.getByTestId('expense-rejection-reason-value')).toHaveTextContent('領収書を添付してください'));
    expect(screen.getByTestId('expense-status-value')).toHaveTextContent('却下');
  });
});
