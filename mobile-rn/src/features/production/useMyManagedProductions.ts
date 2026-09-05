import { useQuery } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchProductions } from './api';

/**
 * StageArt Blueprint再構成 Phase 1: which Productions to surface under
 * the "公演情報" admin menu entry (Blueprint §9 - "単なるProduction
 * Participantだから表示、とはしません"). Shares the exact `['productions']`
 * query key with useOrganizationProductions.ts's own internal query, so
 * this never issues a second network request - it only adds a client-
 * side filter on top of the same cached list, matching that file's own
 * "no server-side Organization-scoped endpoint exists" reasoning.
 */
export function useMyManagedProductions() {
  const { apiClient, status } = useAuth();

  const query = useQuery({
    queryKey: ['productions'],
    queryFn: () => fetchProductions(apiClient),
    enabled: status === 'authenticated',
  });

  return {
    ...query,
    data: query.data?.filter((production) => production.is_primary_manager || !!production.delegate_role),
  };
}
