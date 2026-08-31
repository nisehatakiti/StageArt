import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';
import DashboardScreen from '../app/dashboard';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: jest.fn() }),
}));

/** Kept in its own file - see web-dashboard-greeting.test.tsx's docblock
 * for why (cross-test async-timer leak when several AuthProvider trees
 * mount within one file, not specific to this screen). */
describe('Web Dashboard: 団体（空の場合）', () => {
  it('shows an empty-state prompt to create an Organization when the Person has none', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
    ]);

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <OrganizationProvider>
            <DashboardScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('dashboard-organizations-empty')).toBeVisible());
    expect(screen.queryByTestId('dashboard-organizations-list')).toBeNull();
  });
});
