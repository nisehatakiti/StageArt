import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockBudgetServer, savedBudget } from './__fixtures__/budgetFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('予算策定: 保存済みの予算を一覧から開く', () => {
  it('lists the saved Budget and opens it with its saved amounts', async () => {
    mockBudgetServer({ budgets: [savedBudget] });

    renderRouter('src/app', { initialUrl: '/productions/prod-1/budgets' });

    await waitFor(() => expect(screen.getByTestId('budget-row-bud-9')).toBeVisible(), { timeout: 5000 });
    expect(screen.getByTestId('budget-row-bud-9')).toHaveTextContent(/河童ホームラン2026予算 Version1/);
    expect(screen.getByTestId('budget-row-bud-9')).toHaveTextContent(/下書き/);
    fireEvent.press(screen.getByTestId('budget-row-bud-9'));

    await waitFor(() => expect(screen.getByTestId('budget-item-venue-amount')).toHaveProp('value', '200000'), { timeout: 5000 });
    expect(screen.getByTestId('budget-item-ticket_revenue-amount')).toHaveProp('value', '450000');
    expect(screen.getByTestId('budget-item-travel-amount')).toHaveProp('value', '');
    expect(screen.getByTestId('budget-total-revenue-value')).toHaveTextContent('¥450,000');
    expect(screen.getByTestId('budget-total-expense-value')).toHaveTextContent('¥200,000');
    expect(screen.getByTestId('budget-total-balance-value')).toHaveTextContent('¥250,000');
  });
});
