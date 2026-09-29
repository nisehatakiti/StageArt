import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { cancelParticipant, createNameOnlyParticipant, createPersonParticipant, fetchParticipants, updateParticipant } from './api';

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

export function useCreateNameOnlyParticipant(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: { displayName: string; participantType: string; remarks?: string | null }) =>
      createNameOnlyParticipant(apiClient, productionId as string, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['participants', productionId] }),
  });
}

export function useCreatePersonParticipant(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: { personId: string; participantType: string; remarks?: string | null }) =>
      createPersonParticipant(apiClient, productionId as string, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['participants', productionId] }),
  });
}

export function useUpdateParticipant(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, ...fields }: { id: string; participantType: string; status: string; remarks?: string | null }) =>
      updateParticipant(apiClient, id, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['participants', productionId] }),
  });
}
