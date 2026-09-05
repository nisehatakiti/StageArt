import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { renderHook, waitFor } from '@testing-library/react-native';
import type { PropsWithChildren } from 'react';

import { AuthProvider } from '@/auth/AuthContext';
import { useNavMenu } from '@/components/chrome/useNavMenu';

import { mockFetchRoutes, orgOne, orgTwo, productionOne, productionTwo } from './__fixtures__/homeFixtures';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) =>
    key === 'stageart_access_token' ? 'mock-access-token' : key === 'stageart_refresh_token' ? 'mock-refresh-token' : null
  ),
  setItemAsync: jest.fn(async () => undefined),
  deleteItemAsync: jest.fn(async () => undefined),
}));

function wrapper({ children }: PropsWithChildren) {
  const queryClient = new QueryClient();
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>{children}</AuthProvider>
    </QueryClientProvider>
  );
}

/** StageArt Blueprint再構成 Phase 1 §8/§9: useNavMenu() is the single
 * authorization source WebSidebarNav/NativeDrawerMenu both render from -
 * these tests verify its admin-menu gating directly, independent of
 * either shell's own rendering. */
describe('useNavMenu', () => {
  it('always includes the four basic menu items regardless of admin permission', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => {
      expect(result.current.basicItems.map((item) => item.key)).toEqual([
        'discover-organizations',
        'discover-productions',
        'profile',
        'account',
      ]);
    });
    expect(result.current.adminItems).toEqual([]);
  });

  it('shows 団体情報 only for an Organization where current_person_role is OWNER, not MEMBER', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne, orgTwo] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => {
      const orgAdmin = result.current.adminItems.find((item) => item.key === 'org-admin');
      expect(orgAdmin).toBeDefined();
      expect(orgAdmin?.href).toBe(`/organizations/${orgOne.id}`);
    });
  });

  it('shows 公演情報 for a Production the Person is Primary Manager or Delegate of, ignoring mere Participant status', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [productionOne, productionTwo] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => {
      const productionAdmin = result.current.adminItems.find((item) => item.key === 'production-admin');
      expect(productionAdmin).toBeDefined();
      // Two matching Productions (primary manager + delegate) -> the list screen, not a single deep link.
      expect(productionAdmin?.href).toBe('/participating-productions');
    });
  });

  it('omits both admin items when the Person holds no management permission anywhere', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgTwo] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => {
      expect(result.current.adminItems).toEqual([]);
    });
  });
});
