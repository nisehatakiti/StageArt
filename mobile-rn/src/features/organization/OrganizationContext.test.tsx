import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react-native';
import type { PropsWithChildren } from 'react';

import { AuthProvider, useAuth } from '@/auth/AuthContext';

import { OrganizationProvider, useOrganizationContext } from './OrganizationContext';

const mockSecureStore = new Map<string, string>();

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) => mockSecureStore.get(key) ?? null),
  setItemAsync: jest.fn(async (key: string, value: string) => {
    mockSecureStore.set(key, value);
  }),
  deleteItemAsync: jest.fn(async (key: string) => {
    mockSecureStore.delete(key);
  }),
}));

/**
 * Tested directly via renderHook (the proven-reliable pattern already
 * used by AuthContext.test.tsx), bypassing Expo Router's renderRouter()
 * entirely. renderRouter() forces Jest fake timers - during this Phase,
 * pressing an item inside a 2-Organization picker rendered under a
 * renderRouter()-mounted Stack screen was empirically found to silently
 * drop the resulting state update (reproduced down to a bare useState
 * counter with no Organization Context or navigation involved at all -
 * a Jest/React-Native-Testing-Library/fake-timers environment
 * limitation specific to that render shape, not an application defect).
 * Testing the Context's own logic in isolation, without that renderer,
 * verifies the real behavior without depending on the broken
 * interaction path. See the Phase 5.2 report's Test Results section for
 * the full isolation trail (FlatList removal, side-effect removal,
 * concurrent-query removal, mount-always experiment - each ruled out in
 * turn) and the disclosed Home-level picker-click coverage gap this
 * leaves.
 */
function makeWrapper(queryClient: QueryClient) {
  return function wrapper({ children }: PropsWithChildren) {
    return (
      <QueryClientProvider client={queryClient}>
        <AuthProvider>
          <OrganizationProvider>{children}</OrganizationProvider>
        </AuthProvider>
      </QueryClientProvider>
    );
  };
}

function useHarness() {
  return { auth: useAuth(), organization: useOrganizationContext() };
}

describe('OrganizationContext', () => {
  beforeEach(() => {
    global.fetch = jest.fn();
    mockSecureStore.clear();
  });

  it('starts with no Organization Context selected', async () => {
    const { result } = await renderHook(() => useHarness(), { wrapper: makeWrapper(new QueryClient()) });

    expect(result.current.organization.currentOrganizationId).toBeNull();
  });

  it('selectOrganization sets the current Organization Context', async () => {
    const { result } = await renderHook(() => useHarness(), { wrapper: makeWrapper(new QueryClient()) });

    await act(() => {
      result.current.organization.selectOrganization('org-1');
    });

    expect(result.current.organization.currentOrganizationId).toBe('org-1');
  });

  it('does NOT invalidate Production/Project queries on the first selection (nothing stale to invalidate)', async () => {
    const queryClient = new QueryClient();
    const invalidateSpy = jest.spyOn(queryClient, 'invalidateQueries');
    const { result } = await renderHook(() => useHarness(), { wrapper: makeWrapper(queryClient) });

    await act(() => {
      result.current.organization.selectOrganization('org-1');
    });

    expect(invalidateSpy).not.toHaveBeenCalled();
  });

  it('does NOT invalidate when re-selecting the same Organization', async () => {
    const queryClient = new QueryClient();
    const invalidateSpy = jest.spyOn(queryClient, 'invalidateQueries');
    const { result } = await renderHook(() => useHarness(), { wrapper: makeWrapper(queryClient) });

    await act(() => {
      result.current.organization.selectOrganization('org-1');
    });
    invalidateSpy.mockClear();

    await act(() => {
      result.current.organization.selectOrganization('org-1');
    });

    expect(invalidateSpy).not.toHaveBeenCalled();
  });

  it('§13: invalidates the Production/Project queries on an actual switch to a different Organization', async () => {
    const queryClient = new QueryClient();
    const invalidateSpy = jest.spyOn(queryClient, 'invalidateQueries');
    const { result } = await renderHook(() => useHarness(), { wrapper: makeWrapper(queryClient) });

    await act(() => {
      result.current.organization.selectOrganization('org-1');
    });
    invalidateSpy.mockClear();

    await act(() => {
      result.current.organization.selectOrganization('org-2');
    });

    expect(result.current.organization.currentOrganizationId).toBe('org-2');
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['productions'] });
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['projects'] });
    // Phase 7.5 §17: Dashboard (`['my-dashboard']`) is Organization-横断
    // by design and must never be invalidated by an Organization switch.
    expect(invalidateSpy).not.toHaveBeenCalledWith({ queryKey: ['my-dashboard'] });
  });

  it('resets the Organization Context to null on logout (§7: never leaks across sessions)', async () => {
    (global.fetch as jest.Mock).mockResolvedValueOnce({
      ok: true,
      status: 200,
      text: async () =>
        JSON.stringify({
          access_token: 'access-token-1',
          refresh_token: 'refresh-token-1',
          token_type: 'Bearer',
          expires_in: 3600,
          person_id: 'person-1',
          user_account_id: 'account-1',
          is_new_user: false,
        }),
    });
    // GET /me probe - loginWithEmail() resolves email_verified before
    // deciding whether to establish a session at all (see
    // AuthContext.tsx's docblock); this test needs a real session to
    // exist so it can verify logout resets the Organization Context, so
    // the probe must report an already-verified account.
    (global.fetch as jest.Mock).mockResolvedValueOnce({
      ok: true,
      status: 200,
      text: async () => JSON.stringify({ id: 'person-1', word_press_user_id: 1, email_verified: true }),
    });
    (global.fetch as jest.Mock).mockResolvedValueOnce({
      ok: true,
      status: 200,
      text: async () => JSON.stringify({ success: true }),
    });

    const { result } = await renderHook(() => useHarness(), { wrapper: makeWrapper(new QueryClient()) });
    await waitFor(() => expect(result.current.auth.status).toBe('unauthenticated'));

    await act(async () => {
      await result.current.auth.loginWithEmail('person@example.com', 'password123');
    });

    await act(() => {
      result.current.organization.selectOrganization('org-1');
    });
    expect(result.current.organization.currentOrganizationId).toBe('org-1');

    await act(async () => {
      await result.current.auth.logout();
    });

    expect(result.current.organization.currentOrganizationId).toBeNull();
  });
});
