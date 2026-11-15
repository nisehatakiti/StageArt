import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import type { ReactElement } from 'react';

import { AuthProvider } from '@/auth/AuthContext';
import { mockBudgetServer, savedBudget } from '@/app-tests/budget/__fixtures__/budgetFixtures';

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

  it('edits a saved amount, saves it and shows the stored copy', async () => {
    const server = mockBudgetServer({ budgets: [savedBudget] });
    renderEditor(<BudgetEditor productionId="prod-1" budgetId="bud-9" />);

    await waitFor(() => expect(screen.getByTestId('budget-item-venue-amount')).toHaveProp('value', '200000'), { timeout: 5000 });
    expect(screen.getByTestId('budget-name')).toHaveProp('value', '河童ホームラン2026予算 Version1');
    expect(screen.getByTestId('budget-total-balance-value')).toHaveTextContent('¥250,000');

    fireEvent.changeText(screen.getByTestId('budget-item-venue-amount'), '180000');
    fireEvent.changeText(screen.getByTestId('budget-item-travel-amount'), '12000');
    await waitFor(() => expect(screen.getByTestId('budget-total-balance-value')).toHaveTextContent('¥258,000'));
    fireEvent.press(screen.getByTestId('budget-save'));

    await waitFor(() => expect(screen.getByTestId('budget-saved')).toBeVisible());

    const put = server.calls.find((call) => call.method === 'PUT');
    expect(put?.url).toMatch(/\/budgets\/bud-9$/);
    expect(put?.body).toEqual({
      name: '河童ホームラン2026予算 Version1',
      items: [
        { key: 'ticket_revenue', amount: 450000, notes: null },
        { key: 'venue', amount: 180000, notes: '劇場見積書より' },
        { key: 'travel', amount: 12000, notes: null },
      ],
    });
    expect(screen.getByTestId('budget-item-venue-amount')).toHaveProp('value', '180000');
    expect(screen.getByTestId('budget-item-travel-amount')).toHaveProp('value', '12000');
    expect(screen.getByTestId('budget-total-balance-value')).toHaveTextContent('¥258,000');
    expect(mockReplace).not.toHaveBeenCalled();
  });
});
