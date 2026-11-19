import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { expenseFixture, mockFetchRoutes, ticketManagerProduction } from './__fixtures__/expenseFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));


describe('Expense detail: a delegate without accounting rights', () => {
  it('sees no approval controls', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: ticketManagerProduction },
      { test: (u) => u.endsWith('/expenses/exp-1'), status: 200, body: expenseFixture({ created_by: 'person-1', submitted_by: 'person-1' }) },
    ]);

    renderRouter('src/app', { initialUrl: '/expenses/exp-1' });

    await waitFor(() => expect(screen.getByTestId('expense-withdraw')).toBeVisible());
    expect(screen.queryByTestId('expense-review-actions')).toBeNull();
  });
});
