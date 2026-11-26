import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { mockBudgetServer, savedBudget } from './__fixtures__/budgetFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

describe('予算策定: 採用中の予算', () => {
  it('shows an adopted (ACTIVE) Budget read-only', async () => {
    mockBudgetServer({ budgets: [{ ...savedBudget, status: 'ACTIVE' }] });

    renderRouter('src/app', { initialUrl: '/productions/prod-1/budgets/bud-9' });

    await waitFor(() => expect(screen.getByTestId('budget-read-only')).toBeVisible(), { timeout: 5000 });
    expect(screen.queryByTestId('budget-save')).toBeNull();
    expect(screen.getByTestId('budget-item-venue-amount')).toHaveProp('editable', false);
  });
});
