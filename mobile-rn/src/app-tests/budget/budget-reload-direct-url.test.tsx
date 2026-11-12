import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockBudgetServer, savedBudget } from './__fixtures__/budgetFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** A reload of the Budget URL: everything shown comes back from GET /budgets/{id}. */
describe('予算策定: 予算のURLを再読み込み', () => {
  it('shows the stored name and amounts after a reload', async () => {
    const server = mockBudgetServer({ budgets: [{ ...savedBudget, name: 'Version2', lines: [...savedBudget.lines, { id: 'line-c', account_id: 'acc-travel', amount: 12000, notes: null }] }] });

    renderRouter('src/app', { initialUrl: '/productions/prod-1/budgets/bud-9' });

    await waitFor(() => expect(screen.getByTestId('budget-item-travel-amount')).toHaveProp('value', '12000'), { timeout: 5000 });
    expect(screen.getByTestId('budget-name')).toHaveProp('value', 'Version2');
    expect(screen.getByTestId('budget-item-venue-amount')).toHaveProp('value', '200000');
    expect(screen.getByTestId('budget-total-balance-value')).toHaveTextContent('¥238,000');
    expect(screen.queryByTestId('budget-saved')).toBeNull();
    expect(server.calls.some((call) => call.method === 'GET' && call.url.endsWith('/budgets/bud-9'))).toBe(true);
  });
});
