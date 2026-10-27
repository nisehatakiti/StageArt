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

  it('shows the API refusal when saving fails', async () => {
    mockBudgetServer({ budgets: [{ ...savedBudget, id: 'bud-missing' }] });
    const fetchMock = global.fetch as jest.Mock;
    const passThrough = fetchMock.getMockImplementation() as (input: unknown, init?: RequestInit) => Promise<Response>;
    fetchMock.mockImplementation(async (input: unknown, init?: RequestInit) =>
      (init?.method ?? 'GET').toUpperCase() === 'PUT'
        ? ({ ok: false, status: 403, text: async () => JSON.stringify({ code: 'stageart_budget_access_denied' }) } as Response)
        : passThrough(input, init)
    );
    renderEditor(<BudgetEditor productionId="prod-1" budgetId="bud-missing" />);

    await waitFor(() => expect(screen.getByTestId('budget-item-venue-amount')).toHaveProp('value', '200000'), { timeout: 5000 });
    fireEvent.press(screen.getByTestId('budget-save'));

    await waitFor(() => expect(screen.getByTestId('budget-save-error')).toHaveTextContent(/保存できませんでした。この情報を表示する権限がありません。/));
    expect(screen.queryByTestId('budget-saved')).toBeNull();
  });
});
