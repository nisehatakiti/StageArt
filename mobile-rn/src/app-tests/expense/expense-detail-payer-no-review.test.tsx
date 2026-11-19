import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingManagerProduction, expenseFixture, mockFetchRoutes } from './__fixtures__/expenseFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));


describe('Expense detail: the payer of an Expense', () => {
  it('is not offered approval of an Expense that reimburses them', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: accountingManagerProduction },
      { test: (u) => u.endsWith('/expenses/exp-1'), status: 200, body: expenseFixture({ payer_person_id: 'person-1' }) },
    ]);

    renderRouter('src/app', { initialUrl: '/expenses/exp-1' });

    await waitFor(() => expect(screen.getByTestId('expense-detail')).toBeVisible());
    expect(screen.queryByTestId('expense-review-actions')).toBeNull();
    expect(screen.queryByTestId('expense-applicant-actions')).toBeNull();
  });
});
