import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { cancelParticipant, fetchParticipants } from './api';

export function useParticipants(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['participants', productionId],
    queryFn: () => fetchParticipants(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}

export function useCancelParticipant(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (participantId: string) => cancelParticipant(apiClient, participantId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['participants', productionId] }),
  });
}
