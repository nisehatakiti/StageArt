import { useQuery } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchMyDashboard } from './api';

/**
 * Query Key `['my-dashboard']`: flat, no ID, matching the existing
 * `['me']` / `['organizations']` / `['push-preference']` convention for
 * "the caller's own, non-parameterized resource" (Phase 7.4 §16) - not
 * `['me', 'dashboard']`, which would be the only 2-element key not tied
 * to a specific record ID.
 *
 * Independent of Organization/Production Context on purpose: this Query
 * never depends on `useOrganizationContext()`'s currentOrganizationId,
 * so switching Organization never refetches or clears it (Phase 7.4 §9,
 * §17 - the Dashboard content is Organization-横断 by definition).
 */
export function useMyDashboard() {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['my-dashboard'],
    queryFn: () => fetchMyDashboard(apiClient),
    enabled: status === 'authenticated',
  });
}
