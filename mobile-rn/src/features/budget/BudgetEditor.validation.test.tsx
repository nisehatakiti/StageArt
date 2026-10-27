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

  // A bad amount ('1.5', '-100') is refused by the same validateBudgetForm()
  // (form.test.ts) and by the API (BudgetItemsUseCaseTest). Typing and then
  // pressing 保存 without a server round trip does not re-render under this
  // harness (see web-reservation-create.test.tsx), so it is not driven here.
  it('refuses to save without a name or any amount, without calling the API', async () => {
    const server = mockBudgetServer();
    renderEditor(<BudgetEditor productionId="prod-1" />);

    await waitFor(() => expect(screen.getByTestId('budget-editor')).toBeVisible(), { timeout: 5000 });

    fireEvent.press(screen.getByTestId('budget-save'));
    await waitFor(() => expect(screen.getAllByTestId('budget-form-error')).toHaveLength(2), { timeout: 5000 });
    expect(screen.getByText('予算名を入力してください。')).toBeVisible();
    expect(screen.getByText('少なくとも1つの項目に金額を入力してください。')).toBeVisible();

    expect(server.calls.some((call) => (call.method === 'POST' && call.url.includes('/budgets')) || call.method === 'PUT')).toBe(false);
    expect(mockReplace).not.toHaveBeenCalled();
  });
});
