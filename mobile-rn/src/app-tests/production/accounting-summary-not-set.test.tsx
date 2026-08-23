import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { accountingNotSet, mockFetchRoutes, productionOne } from './__fixtures__/productionShellFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** §4/§11: "Budget未設定" must never be shown as "¥0" - has_budget=false
 * and has_actual=false are distinct, honest states from "the value is
 * zero" (AccountingPolicy.md's "Accounting未開始であることと、Accounting
 * の残高が0円であることは別の状態として扱う"). */
describe('Accounting tab: has_budget=false and has_actual=false', () => {
  it('shows 未設定 / 実績なし / 算出できません, never ¥0', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/productions/prod-1'), status: 200, body: productionOne },
      { test: (u) => u.includes('/productions/prod-1/accounting'), status: 200, body: accountingNotSet },
    ]);

    renderRouter('src/app', { initialUrl: '/production/prod-1/accounting' });

    await waitFor(() => expect(screen.getByTestId('accounting-budget-row')).toBeVisible());

    expect(screen.getByTestId('accounting-budget-row-value')).toHaveTextContent('未設定');
    expect(screen.getByTestId('accounting-actual-row-value')).toHaveTextContent('実績なし');
    expect(screen.getByTestId('accounting-variance-row-value')).toHaveTextContent('算出できません');

    expect(screen.queryByText('¥0')).toBeNull();
  });
});
