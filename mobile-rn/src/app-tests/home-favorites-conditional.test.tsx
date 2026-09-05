import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';

import { AuthProvider } from '@/auth/AuthContext';
import { OrganizationProvider } from '@/features/organization/OrganizationContext';
import type { MyFavorite } from '@/types/api';

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

const oneFavorite: MyFavorite[] = [
  {
    id: 'favorite-1',
    target_type: 'ORGANIZATION',
    target_id: 'org-1',
    target_name: '○○演劇団',
    target_slug: 'oo-gekidan',
    organization_slug: null,
    favorited_at: '2026-01-01T00:00:00+09:00',
  },
];

function renderHome() {
  const queryClient = new QueryClient();
  return render(
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <OrganizationProvider>
          <HomeScreen />
        </OrganizationProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}

/**
 * StageArt Home仕様追加 (2026-09-05): PrimaryNavGrid's お気に入り tile is
 * conditional on useMyFavorites() actually returning at least one row -
 * mirrors home-dashboard-content.test.tsx / web-dashboard-greeting.test.tsx's
 * own isolated-render pattern (mockFetchRoutes + mocked useRouter, no
 * renderRouter()) so each scenario can assert exactly which /me/favorites
 * response produces which Home state. 観劇履歴 has no equivalent test: no
 * audience/viewing-history Domain or API exists anywhere in this codebase
 * (see viewing-history.tsx's own docblock), so there is nothing to gate a
 * test on - it is simply absent from PrimaryNavGrid entirely, unconditionally.
 */
describe('Home: お気に入り conditional tile', () => {
  it('does not show the tile (or the grid at all) when there are zero favorites', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.endsWith('/me/favorites'), status: 200, body: [] },
    ]);

    renderHome();

    await waitFor(() => expect(screen.getByTestId('home-quick-action-create-organization')).toBeVisible());
    expect(screen.queryByTestId('home-primary-nav')).toBeNull();
    expect(screen.queryByTestId('home-nav-favorites')).toBeNull();
  });

  it('shows the tile when at least one favorite exists, and it navigates to /favorites', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.endsWith('/me/favorites'), status: 200, body: oneFavorite },
    ]);

    renderHome();

    await waitFor(() => expect(screen.getByTestId('home-nav-favorites')).toBeVisible());
    fireEvent.press(screen.getByTestId('home-nav-favorites'));
    expect(mockPush).toHaveBeenCalledWith('/favorites');
  });

  it('does not show the tile when the favorites request fails - unconfirmed data is never shown as present', async () => {
    mockFetchRoutes([
      { test: (u) => u.endsWith('/organizations'), status: 200, body: [] },
      { test: (u) => u.endsWith('/me/dashboard'), status: 200, body: myDashboardEmpty },
      { test: (u) => u.endsWith('/me/favorites'), status: 500, body: { message: 'error' } },
    ]);

    renderHome();

    await waitFor(() => expect(screen.getByTestId('home-quick-action-create-organization')).toBeVisible(), { timeout: 8000 });
    expect(screen.queryByTestId('home-nav-favorites')).toBeNull();
  });
});
