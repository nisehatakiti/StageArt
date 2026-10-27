import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import type { ReactElement } from 'react';

import { AuthProvider } from '@/auth/AuthContext';
import { mockBudgetServer } from '@/app-tests/budget/__fixtures__/budgetFixtures';

import { BudgetEditor } from './BudgetEditor';

const mockReplace = jest.fn();
const mockPush = jest.fn();

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ replace: mockReplace, push: mockPush }),
}));

/**
 * 予算策定 (第1スプリント): typing, validation and saving on the Budget
 * input form, rendered on its own. Screen navigation and the redisplay of
 * saved Budgets are covered by src/app-tests/budget/ through renderRouter(),
 * whose harness does not re-render after typing on these screens (the
 * limitation web-reservation-create.test.tsx documents).
 */
function renderEditor(element: ReactElement) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });

  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>{element}</AuthProvider>
    </QueryClientProvider>
  );
}

describe('BudgetEditor', () => {
  beforeEach(() => {
    mockReplace.mockClear();
    mockPush.mockClear();
  });

  it('totals while typing and creates the Budget from plain 予算項目', async () => {
    const server = mockBudgetServer();
    renderEditor(<BudgetEditor productionId="prod-1" />);

    await waitFor(() => expect(screen.getByTestId('budget-editor')).toBeVisible(), { timeout: 5000 });

    fireEvent.changeText(screen.getByTestId('budget-name'), 'A会場案');
    fireEvent.changeText(screen.getByTestId('budget-item-ticket_revenue-amount'), '450,000');
    fireEvent.changeText(screen.getByTestId('budget-item-venue-amount'), '200000');
    fireEvent.changeText(screen.getByTestId('budget-item-travel-amount'), '０');

    await waitFor(() => expect(screen.getByTestId('budget-total-revenue-value')).toHaveTextContent('¥450,000'));
    expect(screen.getByTestId('budget-total-expense-value')).toHaveTextContent('¥200,000');
    expect(screen.getByTestId('budget-total-balance-value')).toHaveTextContent('¥250,000');

    fireEvent.press(screen.getByTestId('budget-save'));

    await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/productions/prod-1/budgets/bud-1?saved=1'));
    const post = server.calls.find((call) => call.method === 'POST' && call.url.includes('/budgets'));
    expect(post?.url).toMatch(/\/productions\/prod-1\/budgets$/);
    expect(post?.body).toEqual({
      name: 'A会場案',
      items: [
        { key: 'ticket_revenue', amount: 450000, notes: null },
        { key: 'venue', amount: 200000, notes: null },
      ],
    });
    expect(server.budgets.get('bud-1')?.lines.map((line) => line.amount)).toEqual([450000, 200000]);
  });
});
