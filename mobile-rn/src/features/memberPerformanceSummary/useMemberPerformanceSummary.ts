import { useQuery } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchMemberPerformanceSummary } from './api';

export function useMemberPerformanceSummary(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['member-performance-summary', productionId],
    queryFn: () => fetchMemberPerformanceSummary(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}
