import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

// PR #7 R10: no activate/complete hooks - the manual start/finish API was
// removed; a confirmed Rehearsal starts/finishes by time on the Backend.
import {
  cancelRehearsal,
  confirmRehearsal,
  createRehearsal,
  fetchRehearsal,
  fetchRehearsals,
  updateRehearsal,
} from './api';

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
    mutationFn: (fields: {
      title?: string | null;
      description?: string | null;
      // PR #7 review (R7): the screens send it (f2de669 / create), the API
      // client already maps it to activity_type - only this type lacked it.
      activityType?: string;
      startDateTime?: string;
      endDateTime?: string;
      timezone?: string;
      location?: string;
      targetPersonIds?: string[];
      responseDeadline?: string;
      confirmOnCreate?: boolean;
      idempotencyKey?: string;
    }) => createRehearsal(apiClient, productionId as string, fields),
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

export function useCancelRehearsal(rehearsalId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => cancelRehearsal(apiClient, rehearsalId as string),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['rehearsal', rehearsalId] }),
  });
}

export function useUpdateRehearsal(rehearsalId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: {
      title?: string | null;
      description: string | null;
      activityType?: string;
      startDateTime?: string;
      endDateTime?: string;
      timezone?: string;
      location?: string;
      responseDeadline?: string;
    }) => updateRehearsal(apiClient, rehearsalId as string, fields),
    onSuccess: (rehearsal) => {
      queryClient.invalidateQueries({ queryKey: ['rehearsal', rehearsalId] });
      queryClient.invalidateQueries({ queryKey: ['production-rehearsals', rehearsal.production_id] });
    },
  });
}
