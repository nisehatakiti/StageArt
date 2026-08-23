import { useQuery } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchRehearsal, fetchRehearsals } from './api';

export function useRehearsals(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['production-rehearsals', productionId],
    queryFn: () => fetchRehearsals(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}

export function useRehearsal(rehearsalId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['rehearsal', rehearsalId],
    queryFn: () => fetchRehearsal(apiClient, rehearsalId as string),
    enabled: status === 'authenticated' && !!rehearsalId,
  });
}
