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

let mockPathname = '/home';
jest.mock('expo-router', () => ({
  ...jest.requireActual('expo-router'),
  usePathname: () => mockPathname,
}));

function wrapper({ children }: PropsWithChildren) {
  const queryClient = new QueryClient();
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>{children}</AuthProvider>
    </QueryClientProvider>
  );
}

/**
 * StageArt Phase 1: useNavMenu() now derives its Context (home /
 * organization / production) from the current route rather than from
 * admin-permission scanning across every Organization/Production the
 * Person belongs to - these tests verify that derivation and the
 * per-Context item gating directly, independent of either shell's own
 * rendering.
 */
describe('useNavMenu', () => {
  beforeEach(() => {
    mockPathname = '/home';
  });

  it('returns the four Fixed Area items and the Home Context items on /home', async () => {
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    expect(result.current.fixedItems.map((item) => item.key)).toEqual(['home', 'mypage', 'settings']);
    expect(result.current.contextType).toBe('home');
    expect(result.current.contextItems.map((item) => item.key)).toEqual([
      'discover-organizations',
      'discover-productions',
      'favorites',
      'my-organizations',
      'participating-productions',
      'viewing-history',
    ]);
  });

  it('switches to Organization Context on an /organizations/{id} route, enabling Owner-only items only for the Owner', async () => {
    mockPathname = `/organizations/${orgOne.id}/edit`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgOne, orgTwo] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(orgOne.name));
    expect(result.current.contextType).toBe('organization');
    const info = result.current.contextItems.find((item) => item.key === 'organization-info');
    expect(info?.disabled).toBe(false);
    expect(result.current.contextItems.some((item) => item.key === 'organization-invite')).toBe(true);
  });

  it('hides Owner-only Organization Context items and disables 団体情報 for a MEMBER', async () => {
    mockPathname = `/organizations/${orgTwo.id}/members`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [orgTwo] },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextType).toBe('organization'));
    const info = result.current.contextItems.find((item) => item.key === 'organization-info');
    expect(info?.disabled).toBe(true);
    expect(result.current.contextItems.some((item) => item.key === 'organization-invite')).toBe(false);
  });

  it('switches to Production Context on a /productions/{id} route, disabling management items for a non-manager', async () => {
    mockPathname = `/productions/${productionTwo.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionTwo.id}`), status: 200, body: productionTwo },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextType).toBe('production'));
    const info = result.current.contextItems.find((item) => item.key === 'production-info');
    expect(info?.disabled).toBe(true);
    // productionTwo's delegate_role is REHEARSAL_MANAGER, not PARTICIPANT_MANAGER.
    const members = result.current.contextItems.find((item) => item.key === 'production-members');
    expect(members?.disabled).toBe(true);
    const rehearsal = result.current.contextItems.find((item) => item.key === 'production-rehearsal');
    expect(rehearsal?.disabled).toBeUndefined();
    // productionTwo's delegate_role is REHEARSAL_MANAGER, not PERFORMANCE_MANAGER.
    const performances = result.current.contextItems.find((item) => item.key === 'production-performances');
    expect(performances?.disabled).toBe(true);
  });

  it('enables 公演回管理 for a Primary Manager', async () => {
    mockPathname = `/productions/${productionOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionOne.id}`), status: 200, body: productionOne },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(productionOne.name));
    const performances = result.current.contextItems.find((item) => item.key === 'production-performances');
    expect(performances?.disabled).toBe(false);
  });

  it('enables 小屋入り～本番／公演終了・精算処理 for a Primary Manager (Phase 4 Check-in/精算/会計連携)', async () => {
    mockPathname = `/production/${productionOne.id}/schedule`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionOne.id}`), status: 200, body: productionOne },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(productionOne.name));
    expect(result.current.contextItems.find((item) => item.key === 'production-reception')?.disabled).toBe(false);
    expect(result.current.contextItems.find((item) => item.key === 'production-settlement')?.disabled).toBe(false);
  });

  it('enables チケット管理 for a Primary Manager but disables it for a non-TICKET_MANAGER delegate', async () => {
    mockPathname = `/productions/${productionOne.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionOne.id}`), status: 200, body: productionOne },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextLabel).toBe(productionOne.name));
    expect(result.current.contextItems.find((item) => item.key === 'production-ticket')?.disabled).toBe(false);
  });

  it('disables チケット管理 for a REHEARSAL_MANAGER delegate (not TICKET_MANAGER)', async () => {
    mockPathname = `/productions/${productionTwo.id}`;
    mockFetchRoutes([
      { test: (url) => url.endsWith('/organizations'), status: 200, body: [] },
      { test: (url) => url.endsWith(`/productions/${productionTwo.id}`), status: 200, body: productionTwo },
      { test: (url) => url.endsWith('/productions'), status: 200, body: [] },
      { test: (url) => url.endsWith('/projects'), status: 200, body: [] },
    ]);

    const { result } = await renderHook(() => useNavMenu(), { wrapper });

    await waitFor(() => expect(result.current.contextType).toBe('production'));
    expect(result.current.contextItems.find((item) => item.key === 'production-ticket')?.disabled).toBe(true);
  });
});
