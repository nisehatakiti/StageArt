import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import {
  acceptParticipantInvitation,
  cancelParticipantInvitation,
  createParticipantInvitation,
  fetchMyParticipantInvitations,
  fetchParticipantInvitations,
  rejectParticipantInvitation,
  resendParticipantInvitation,
} from './api';

export const MY_PARTICIPANT_INVITATIONS_KEY = ['my-participant-invitations'] as const;

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
    // R14-B: an invitation adds nobody, so only the invitation list changes.
    onSettled: () => queryClient.invalidateQueries({ queryKey: ['participant-invitations', productionId] }),
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

/** R14-B §7.2: GET /me/participant-invitations (login required). */
export function useMyParticipantInvitations() {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: MY_PARTICIPANT_INVITATIONS_KEY,
    queryFn: () => fetchMyParticipantInvitations(apiClient),
    enabled: status === 'authenticated',
  });
}

/**
 * Accept / reject: the result is judged from the API's answer (200 / 404 /
 * 409 / 410 ...), never from the list's can_accept (§7.2, N5). Afterwards
 * the own invitation list, the participation list and the dashboard are
 * refetched.
 */
export function useRespondToParticipantInvitation() {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: MY_PARTICIPANT_INVITATIONS_KEY });
    queryClient.invalidateQueries({ queryKey: ['my-participating-productions'] });
    queryClient.invalidateQueries({ queryKey: ['my-dashboard'] });
    queryClient.invalidateQueries({ queryKey: ['productions'] });
  };

  const accept = useMutation({
    mutationFn: (invitationId: string) => acceptParticipantInvitation(apiClient, invitationId),
    onSettled: refresh,
  });
  const reject = useMutation({
    mutationFn: (invitationId: string) => rejectParticipantInvitation(apiClient, invitationId),
    onSettled: refresh,
  });

  return { accept, reject };
}
