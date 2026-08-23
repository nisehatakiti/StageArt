import { useQuery } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchProductionAccounting } from './api';

/**
 * §13/§36: Query Key is `['production-accounting', productionId]`, the
 * exact key this Phase's own instruction specifies, so a future screen
 * (e.g. a Budget editor) can invalidate this one Query without guessing
 * its shape.
 */
export function useProductionAccounting(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['production-accounting', productionId],
    queryFn: () => fetchProductionAccounting(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}
