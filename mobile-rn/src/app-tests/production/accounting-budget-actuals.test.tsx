import { renderRouter, screen, waitFor, within } from 'expo-router/testing-library';

import { accountingFull, mockFetchRoutes, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const budgetActuals = {
  production_id: 'prod-1',
  has_budget: true,
  categories: [
    { account_id: 'acc-ticket', name: 'チケット売上', type: 'REVENUE', budget: 300000, actual: 50000 },
    { account_id: 'acc-venue', name: '会場費', type: 'EXPENSE', budget: 120000, actual: 0 },
    { account_id: 'acc-consumables', name: '消耗品費', type: 'EXPENSE', budget: 0, actual: 3000 },
  ],
  transactions: [
    {
      journal_entry_id: 'je-1',
      journal_date: '2026-11-01',
      description: '当日券',
      account_id: 'acc-ticket',
      account_name: 'チケット売上',
      type: 'REVENUE',
      income: 50000,
      expense: null,
    },
    {
      journal_entry_id: 'je-2',
      journal_date: '2026-11-02',
      description: '養生テープ',
      account_id: 'acc-consumables',
      account_name: '消耗品費',
      type: 'EXPENSE',
      income: null,
      expense: 3000,
    },
  ],
  currency: 'JPY',
};

/** 公演会計: 費目別の予算・実績と取引明細 (OrganizationAccountingAndProductionCostPolicy.md §3). */
describe('Accounting tab: 費目別 and 取引明細', () => {
  it('shows each category with its budget and actual, and each transaction', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/accounting'), status: 200, body: accountingFull },
      { test: (u) => u.endsWith('/productions/prod-1/budget-actuals'), status: 200, body: budgetActuals },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/accounting' });

    await waitFor(() => expect(screen.getByTestId('accounting-categories')).toBeVisible(), { timeout: 5000 });

    const venue = within(screen.getByTestId('accounting-category-acc-venue'));
    expect(venue.getByText('会場費')).toBeVisible();
    expect(venue.getByText('¥120,000')).toBeVisible();
    expect(venue.getByText('¥0')).toBeVisible();
    expect(within(screen.getByTestId('accounting-category-acc-consumables')).getByText('¥3,000')).toBeVisible();

    expect(within(screen.getByTestId('accounting-transaction-0')).getByText('当日券')).toBeVisible();
    expect(screen.getByTestId('accounting-transaction-0-amount')).toHaveTextContent('収入 ¥50,000');
    expect(within(screen.getByTestId('accounting-transaction-1')).getByText('消耗品費')).toBeVisible();
    expect(screen.getByTestId('accounting-transaction-1-amount')).toHaveTextContent('支出 ¥3,000');
  });
});
