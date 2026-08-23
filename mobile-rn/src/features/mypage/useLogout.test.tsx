import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { act, renderHook, waitFor } from '@testing-library/react-native';
import type { PropsWithChildren } from 'react';

import { AuthProvider, useAuth } from '@/auth/AuthContext';

import { useLogout } from './useLogout';

const mockSecureStore = new Map<string, string>();
const mockReplace = jest.fn();

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn(async (key: string) => mockSecureStore.get(key) ?? null),
  setItemAsync: jest.fn(async (key: string, value: string) => {
    mockSecureStore.set(key, value);
  }),
  deleteItemAsync: jest.fn(async (key: string) => {
    mockSecureStore.delete(key);
  }),
}));

jest.mock('expo-router', () => ({
  useRouter: () => ({ replace: mockReplace }),
}));

/**
 * §31: after Logout, no previous user's Server Data (Production /
 * Accounting / Notification / Participant / Push Preference) may remain
 * in the Query Cache for a different user who logs in next on the same
 * device. Tested directly against a real QueryClient here (rather than
 * only through the rendered screen) so the cache-clearing behavior
 * itself - not just its downstream UI effect - is verified.
 */
describe('useLogout', () => {
  beforeEach(() => {
    global.fetch = jest.fn(async (input: unknown) => {
      const url = String(input);
      if (url.endsWith('/auth/refresh')) {
        return {
          ok: true,
          status: 200,
          text: async () => JSON.stringify({ access_token: 'refreshed-token', token_type: 'Bearer', expires_in: 3600 }),
        } as Response;
      }
      if (url.endsWith('/auth/logout')) {
        return { ok: true, status: 200, text: async () => JSON.stringify({ success: true }) } as Response;
      }
      throw new Error(`Unmocked fetch: ${url}`);
    });
    mockSecureStore.clear();
    mockSecureStore.set('stageart_access_token', 'stored-access-token');
    mockSecureStore.set('stageart_refresh_token', 'stored-refresh-token');
    mockReplace.mockClear();
  });

  it('clears SecureStore credentials, clears the Query cache, and navigates to /login', async () => {
    const queryClient = new QueryClient();
    queryClient.setQueryData(['production-accounting', 'prod-1'], { total_budget: 300000 });
    queryClient.setQueryData(['push-preference'], { enabled: true, updated_at: null });
    // Phase 7.5 §26: the Dashboard cache must be cleared on logout too.
    queryClient.setQueryData(['my-dashboard'], { upcoming_rehearsals: [], notifications: [] });

    function wrapper({ children }: PropsWithChildren) {
      return (
        <QueryClientProvider client={queryClient}>
          <AuthProvider>{children}</AuthProvider>
        </QueryClientProvider>
      );
    }

    const { result } = await renderHook(
      () => ({ auth: useAuth(), logout: useLogout() }),
      { wrapper }
    );

    await waitFor(() => expect(result.current.auth.status).toBe('authenticated'));

    await act(async () => {
      await result.current.logout();
    });

    expect(mockSecureStore.has('stageart_access_token')).toBe(false);
    expect(mockSecureStore.has('stageart_refresh_token')).toBe(false);
    expect(queryClient.getQueryData(['production-accounting', 'prod-1'])).toBeUndefined();
    expect(queryClient.getQueryData(['push-preference'])).toBeUndefined();
    expect(queryClient.getQueryData(['my-dashboard'])).toBeUndefined();
    expect(mockReplace).toHaveBeenCalledWith('/login');
  });
});
