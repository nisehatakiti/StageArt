import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

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

const mockPush = jest.fn();

jest.mock('expo-router', () => ({
  useRouter: () => ({ push: mockPush }),
}));

/**
 * StageArt Web版 再設計 Phase 2続き: dashboard.tsx is the destination
 * WebLayout's own header logo / sidebar top item already point to (see
 * WebLayout.tsx's TOP_LEVEL_ITEMS) - this is the first coverage either
 * dashboard.tsx or WebLayout has had (WebLayout renders as part of this
 * screen's tree, unmocked).
 *
 * §6 (home-multi-org-switch.test.tsx's own docblock): kept one behavior
 * per file - a full AuthProvider/QueryClientProvider tree's async boot
 * (SecureStore -> /auth/refresh -> /me) was found to leave a react-query
 * notifyManager-scheduled timer still pending past a test's own
 * assertions when several such trees are mounted back-to-back inside one
 * file, causing later tests in that file to intermittently fail to find
 * elements that render correctly in isolation - the same class of
 * cross-test leak already disclosed there, not a defect in this screen.
 */
describe('Web Dashboard: greeting + quick actions', () => {
  it('greets the current Person by family name and offers the primary Web entry points', async () => {
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

    await waitFor(() => expect(screen.getByTestId('dashboard-greeting')).toBeVisible());
    expect(screen.getByText(/舞台さん/)).toBeVisible();

    fireEvent.press(screen.getByTestId('dashboard-quick-action-create-organization'));
    expect(mockPush).toHaveBeenCalledWith('/organizations/create');

    fireEvent.press(screen.getByTestId('dashboard-quick-action-discover-organizations'));
    expect(mockPush).toHaveBeenCalledWith('/discover-organizations');
  });
});
