import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import {
  cancelParticipant,
  createNameOnlyParticipant,
  fetchMyParticipatingProductions,
  fetchParticipants,
  updateParticipant,
  withdrawParticipation,
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

export function useUpdateParticipant(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ id, ...fields }: { id: string; participantType: string; remarks: string | null }) =>
      updateParticipant(apiClient, id, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['participants', productionId] }),
  });
}

/**
 * R14-B §7.2: POST /participants/{id}/withdraw. Afterwards everything that
 * depends on the Person's participation or delegate roles is refetched -
 * the delegate roles in the Production end with the withdrawal (D9).
 */
export function useWithdrawParticipation() {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (participantId: string) => withdrawParticipation(apiClient, participantId),
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['my-participating-productions'] });
      queryClient.invalidateQueries({ queryKey: ['productions'] });
      queryClient.invalidateQueries({ queryKey: ['production'] });
      queryClient.invalidateQueries({ queryKey: ['my-dashboard'] });
      queryClient.invalidateQueries({ queryKey: ['participants'] });
      queryClient.invalidateQueries({ queryKey: ['my-delegate-roles'] });
    },
  });
}
