import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';

import { myDashboardEmpty, orgOne } from './__fixtures__/homeFixtures';
import { accountingFull, mockFetchRoutes, productionOne } from './production/__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

const productionResults = {
  organization_id: orgOne.id,
  productions: [
    { production_id: 'prod-1', name: '春公演', revenue: 200000, cost: 125000, profit: 75000, closing_confirmed: true },
    { production_id: 'prod-2', name: '秋公演', revenue: 0, cost: 80000, profit: -80000, closing_confirmed: false },
  ],
  totals: { revenue: 200000, cost: 205000, profit: -5000 },
  currency: 'JPY',
};

/**
 * 団体会計の公演別実績 (OrganizationAccountingAndProductionCostPolicy.md §2):
 * 売上 / 原価 / 収支 / 決算状態 per Production with the totals, and each
 * Production opens its own 会計 screen for the 費目別 detail.
 */
describe('Web 団体管理: 会計管理の公演別実績', () => {
  it('lists each Production and the totals, and opens the Production 会計 from a row', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [{ ...orgOne, accounting_enabled: true }] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.endsWith(`/organizations/${orgOne.id}/production-results`), status: 200, body: productionResults },
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.endsWith('/productions/prod-1/accounting'), status: 200, body: accountingFull },
    ]);

    renderRouter('src/app', { initialUrl: `/organizations/${orgOne.id}/accounting` });

    await waitFor(() => expect(screen.getByTestId('organization-production-results')).toBeVisible(), { timeout: 5000 });

    const spring = within(screen.getByTestId('organization-production-result-prod-1'));
    expect(spring.getByText('春公演')).toBeVisible();
    expect(spring.getByText('確定済み')).toBeVisible();
    expect(spring.getByText('売上 ¥200,000')).toBeVisible();
    expect(spring.getByText('原価 ¥125,000')).toBeVisible();
    expect(spring.getByText('収支 ¥75,000')).toBeVisible();

    const autumn = within(screen.getByTestId('organization-production-result-prod-2'));
    expect(autumn.getByText('確定前')).toBeVisible();
    expect(autumn.getByText('収支 -¥80,000')).toBeVisible();

    const total = within(screen.getByTestId('organization-production-results-total'));
    expect(total.getByText('原価 ¥205,000')).toBeVisible();
    expect(total.getByText('収支 -¥5,000')).toBeVisible();

    fireEvent.press(screen.getByTestId('organization-production-result-prod-1'));

    await waitFor(() => expect(screen.getByTestId('accounting-budget-row-value')).toHaveTextContent('¥300,000'), { timeout: 5000 });
  });
});
