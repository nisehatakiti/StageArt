import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';

import { mockFetchRoutes, myDashboardEmpty } from './__fixtures__/homeFixtures';
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
 * StageArt Blueprint再構成 Phase 1c: `/dashboard` no longer has its own
 * screen content (it is a plain redirect stub to /home) - this test
 * previously rendered the old `DashboardScreen`, now renders the single
 * canonical `HomeScreen` instead, since the greeting + quick-create
 * actions it asserts on live there now.
 *
 * §6 (home-multi-org-switch.test.tsx's own docblock): kept one behavior
 * per file - a full AuthProvider/QueryClientProvider tree's async boot
 * was found to leave a react-query notifyManager-scheduled timer still
 * pending past a test's own assertions when several such trees are
 * mounted back-to-back inside one file.
 *
 * StageArt Home仕様追加 (2026-09-05): PrimaryNavGrid's only remaining
 * tile (お気に入り) is now conditional on actually having a favorite -
 * this test supplies one via its own /me/favorites route (overriding
 * homeFixtures.ts's default empty-array fallback) specifically so that
 * tile is present to press.
 */
describe('Home: greeting + quick actions', () => {
  it('greets the current Person by family name and offers the primary entry points', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      {
        test: (url) => url.endsWith('/me/favorites'),
        status: 200,
        body: [
          {
            id: 'favorite-1',
            target_type: 'ORGANIZATION',
            target_id: 'org-1',
            target_name: '○○演劇団',
            target_slug: 'oo-gekidan',
            organization_slug: null,
            favorited_at: '2026-01-01T00:00:00+09:00',
          },
        ],
      },
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

    await waitFor(() => expect(screen.getByTestId('home-greeting')).toBeVisible());
    expect(screen.getByText(/舞台さん/)).toBeVisible();

    fireEvent.press(screen.getByTestId('home-quick-action-create-organization'));
    expect(mockPush).toHaveBeenCalledWith('/organizations/create');

    await waitFor(() => expect(screen.getByTestId('home-nav-favorites')).toBeVisible());
    fireEvent.press(screen.getByTestId('home-nav-favorites'));
    expect(mockPush).toHaveBeenCalledWith('/favorites');
  });
});
