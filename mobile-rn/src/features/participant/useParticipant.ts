import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import {
  cancelParticipant,
  createNameOnlyParticipant,
  createPersonParticipant,
  fetchMyParticipatingProductions,
  fetchParticipants,
  updateParticipant,
} from './api';

export function useParticipants(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['participants', productionId],
    queryFn: () => fetchParticipants(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}

/** 「参加している公演・活動」の正式なデータソース - see fetchMyParticipatingProductions()'s own docblock. */
export function useMyParticipatingProductions() {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['my-participating-productions'],
    queryFn: () => fetchMyParticipatingProductions(apiClient),
    enabled: status === 'authenticated',
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
