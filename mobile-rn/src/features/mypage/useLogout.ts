import { useQueryClient } from '@tanstack/react-query';
import { useRouter } from 'expo-router';
import { useCallback } from 'react';

import { useAuth } from '@/auth/AuthContext';

/**
 * §30/§31: the full Logout sequence - (1) Auth状態解除 + (2) SecureStore
 * credential削除 both already happen inside AuthContext.logout() (§37
 * keeps AuthContext scoped to auth state only, so this hook does not
 * duplicate that logic); (3) TanStack Query cache clear and (4) navigate
 * to /login happen here, since those are consequences of logging out
 * triggered from the UI, not Auth state itself. queryClient.clear()
 * removes every cached query (Production/Accounting/Notification/
 * Participant/Push Preference included) so no previous user's Server
 * Data can be shown to whoever logs in next on the same device (§31).
 */
export function useLogout() {
  const { logout } = useAuth();
  const queryClient = useQueryClient();
  const router = useRouter();

  return useCallback(async () => {
    await logout();
    queryClient.clear();
    router.replace('/login');
  }, [logout, queryClient, router]);
}
