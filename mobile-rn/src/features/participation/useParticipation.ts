import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import {
  approveParticipationRequest,
  fetchPendingParticipationRequests,
  rejectParticipationRequest,
  requestProductionParticipation,
} from './api';

export function usePendingParticipationRequests(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['pending-participation-requests', productionId],
    queryFn: () => fetchPendingParticipationRequests(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}

export function useRequestProductionParticipation() {
  const { apiClient } = useAuth();

  return useMutation({
    mutationFn: (fields: { productionId?: string; joinKeyCode?: string; participantType: string }) =>
      requestProductionParticipation(apiClient, fields),
  });
}

export function useParticipationRequestDecision(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['pending-participation-requests', productionId] });
  }

  const approve = useMutation({
    mutationFn: (participantId: string) => approveParticipationRequest(apiClient, participantId),
    onSuccess: invalidate,
  });

  const reject = useMutation({
    mutationFn: (participantId: string) => rejectParticipationRequest(apiClient, participantId),
    onSuccess: invalidate,
  });

  return { approve, reject };
}
