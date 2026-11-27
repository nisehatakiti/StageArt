import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import {
  cancelParticipantInvitation,
  createParticipantInvitation,
  fetchMyParticipantInvitation,
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
    // A verified account is registered at once (MEMBER_ADDED): the member list changes too.
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['participant-invitations', productionId] });
      queryClient.invalidateQueries({ queryKey: ['participants', productionId] });
    },
  });
}

export function useResendParticipantInvitation(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (invitationId: string) => resendParticipantInvitation(apiClient, invitationId),
    // Refetched after a refusal too: send status / limits may have changed.
    onSettled: () => queryClient.invalidateQueries({ queryKey: ['participant-invitations', productionId] }),
  });
}

export function useCancelParticipantInvitation(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (invitationId: string) => cancelParticipantInvitation(apiClient, invitationId),
    onSettled: () => queryClient.invalidateQueries({ queryKey: ['participant-invitations', productionId] }),
  });
}

/** GET /me/participant-invitations/{id} - the screen the invitation mail's link opens (login required). */
export function useMyParticipantInvitation(invitationId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['my-participant-invitation', invitationId],
    queryFn: () => fetchMyParticipantInvitation(apiClient, invitationId as string),
    enabled: status === 'authenticated' && !!invitationId,
    retry: false,
  });
}
