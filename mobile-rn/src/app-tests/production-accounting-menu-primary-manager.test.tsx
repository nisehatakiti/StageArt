import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { Text } from 'react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { WebSidebarNav } from '@/components/chrome/WebSidebarNav';

import { mockFetchRoutes, productionOne } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

let mockPathname = '/home';
const mockPush = jest.fn();
jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  usePathname: () => mockPathname,
  useRouter: () => ({ push: mockPush, replace: jest.fn(), back: jest.fn() }),
}));

function renderSidebar(production: typeof productionOne) {
  mockPathname = `/productions/${production.id}`;
  mockFetchRoutes([
    { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
    { test: (url) => url.endsWith(`/productions/${production.id}`), status: 200, body: production },
    { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
    { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
  ]);

  return render(
    <QueryClientProvider client={new QueryClient()}>
      <AuthProvider>
        <WebSidebarNav>
          <Text>content</Text>
        </WebSidebarNav>
      </AuthProvider>
    </QueryClientProvider>
  );
}

/**
 * 会計メニューへの導線 (ManagementNavigationPolicy.md "Production Context":
 * 公演 > 会計 > 予算 / 実績): inside a Production, the 会計 heading with 予算
 * (Budget list) and 実績 (Production Accounting Summary) for the
 * PrimaryManager or an ACCOUNTING_MANAGER, each opening that same
 * Production's screen.
 */
describe('Production menu: 会計', () => {
  beforeEach(() => {
    mockPush.mockClear();
  });

  it('shows 会計 > 予算 / 実績 to the PrimaryManager and opens this Production’s screens', async () => {
    renderSidebar(productionOne);

    await waitFor(() => expect(screen.getByTestId('web-chrome-nav-production-accounting-budget')).toBeVisible(), { timeout: 5000 });
    expect(screen.getByTestId('web-chrome-group-production-accounting-budget')).toHaveTextContent('会計');
    expect(screen.getByTestId('web-chrome-nav-production-accounting-budget')).toHaveTextContent('予算');
    expect(screen.getByTestId('web-chrome-nav-production-accounting')).toHaveTextContent('実績');

    fireEvent.press(screen.getByTestId('web-chrome-nav-production-accounting-budget'));
    expect(mockPush).toHaveBeenLastCalledWith(`/productions/${productionOne.id}/budgets`);

    fireEvent.press(screen.getByTestId('web-chrome-nav-production-accounting'));
    expect(mockPush).toHaveBeenLastCalledWith(`/production/${productionOne.id}/accounting`);
  });
});
