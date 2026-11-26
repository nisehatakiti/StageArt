import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockBudgetServer } from './__fixtures__/budgetFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

/** 会計メニュー → 予算の一覧 → 新しい予算: the screen lists plain 予算項目, not Accounts. */
describe('予算策定: 会計タブから予算画面へ', () => {
  it('opens the Budget list from the 会計 tab and a new Budget with plain items', async () => {
    mockBudgetServer();

    renderRouter('src/app', { initialUrl: '/production/prod-1/accounting' });

    await waitFor(() => expect(screen.getByTestId('accounting-budgets-link')).toBeVisible(), { timeout: 5000 });
    expect(screen.getByTestId('accounting-budgets-link')).toHaveTextContent('予算を入力・確認する');
    fireEvent.press(screen.getByTestId('accounting-budgets-link'));

    await waitFor(() => expect(screen.getByTestId('budgets-empty')).toBeVisible(), { timeout: 5000 });
    fireEvent.press(screen.getByTestId('budgets-create'));

    await waitFor(() => expect(screen.getByTestId('budget-editor')).toBeVisible(), { timeout: 5000 });
    expect(screen.getByTestId('budget-section-REVENUE')).toHaveTextContent(/チケット収入/);
    expect(screen.getByTestId('budget-section-REVENUE')).toHaveTextContent(/助成金・補助金、協賛・寄付など/);
    expect(screen.getByTestId('budget-section-EXPENSE')).toHaveTextContent(/会場費/);
    expect(screen.getByTestId('budget-section-EXPENSE')).toHaveTextContent(/公共交通機関・レンタカー・駐車場など/);
    expect(screen.getByTestId('budget-total-balance-value')).toHaveTextContent('¥0');
    expect(screen.getByTestId('budget-save')).toBeVisible();
    expect(screen.queryByText(/勘定科目/)).toBeNull();
  });
});
