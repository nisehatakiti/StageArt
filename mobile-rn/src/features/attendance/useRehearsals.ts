import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { confirmRehearsal, createRehearsal, fetchRehearsal, fetchRehearsals } from './api';

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

export function useCreateRehearsal(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: { title: string; startDateTime?: string; endDateTime?: string; timezone?: string; location?: string }) =>
      createRehearsal(apiClient, productionId as string, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['production-rehearsals', productionId] }),
  });
}

export function useConfirmRehearsal(rehearsalId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => confirmRehearsal(apiClient, rehearsalId as string),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['rehearsal', rehearsalId] }),
  });
}
