import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import {
  cancelParticipantInvitation,
  createParticipantInvitation,
  fetchParticipantInvitations,
  resendParticipantInvitation,
} from './api';

export function useParticipantInvitations(productionId: string | undefined, enabled: boolean) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['participant-invitations', productionId],
    queryFn: () => fetchParticipantInvitations(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId && enabled,
  });
}

export function useCreateParticipantInvitation(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: { name: string; email: string; participantType: string; remarks?: string | null }) =>
      createParticipantInvitation(apiClient, productionId as string, fields),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['participants', productionId] });
      queryClient.invalidateQueries({ queryKey: ['participant-invitations', productionId] });
    },
  });
}

export function useResendParticipantInvitation(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (invitationId: string) => resendParticipantInvitation(apiClient, invitationId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['participant-invitations', productionId] }),
  });
}

export function useCancelParticipantInvitation(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (invitationId: string) => cancelParticipantInvitation(apiClient, invitationId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['participant-invitations', productionId] }),
  });
}
