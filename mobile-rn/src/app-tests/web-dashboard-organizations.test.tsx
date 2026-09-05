import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, myDashboardEmpty, orgOne, orgTwo, productionOne, productionTwo, projectOne, projectTwo } from './__fixtures__/homeFixtures';
import HomeScreen from '../app/(app)/home';

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
 * StageArt Blueprint再構成 Phase 1c: renders the single canonical
 * HomeScreen (see web-dashboard-greeting.test.tsx's docblock for why).
 * Distinct from home-single-org.test.tsx/home-multi-org-switch.test.tsx
 * in asserting the 公開状態 pill ("下書き") specifically, which those
 * files do not check.
 */
describe('Home: 団体（参加中／管理中）', () => {
  it('lists each Organization with its Role, 公開状態 and own Productions, and tapping navigates to its management screen', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne, orgTwo] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [productionOne, productionTwo] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [projectOne, projectTwo] },
    ]);

    const queryClient = new QueryClient();
    render(
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <OrganizationProvider>
            <HomeScreen />
          </OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );

    await waitFor(() => expect(screen.getByTestId('home-organization-row-org-1')).toBeVisible());
    const orgOneRow = within(screen.getByTestId('home-organization-row-org-1'));
    expect(orgOneRow.getByText('○○演劇団')).toBeVisible();
    expect(orgOneRow.getByText('オーナー')).toBeVisible();
    expect(orgOneRow.getByText('下書き')).toBeVisible();

    await waitFor(() => expect(orgOneRow.getByText(/○○公演2026/)).toBeVisible());

    fireEvent.press(screen.getByTestId('home-organization-link-org-1'));
    expect(mockPush).toHaveBeenCalledWith('/organizations/org-1');
  });
});
