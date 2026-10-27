import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockBudgetServer } from './__fixtures__/budgetFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('予算策定: 権限のない利用者', () => {
  it('shows the refusal when the API refuses the Budget list (403)', async () => {
    mockBudgetServer({ budgetsStatus: 403 });

    renderRouter('src/app', { initialUrl: '/productions/prod-1/budgets' });

    await waitFor(() => expect(screen.getByTestId('budgets-error')).toHaveTextContent('この情報を表示する権限がありません。'), { timeout: 5000 });
    expect(screen.queryByTestId('budgets-create')).toBeNull();
  });
});
