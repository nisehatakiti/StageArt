import type { ApiClient } from '@/api/client';
import { publicGet } from '@/api/publicClient';
import type { CreateParticipantInvitationResult, ParticipantInvitation, ParticipantInvitationPreview } from '@/types/api';

/**
 * StageArt メール招待によるProductionParticipant追加機能 §16/§17: one
 * call handles both outcomes - an email that already belongs to an
 * existing StageArt Person is added directly as a Participant, an
 * unregistered email becomes a ParticipantInvitation (or resends an
 * existing usable one) - see CreateParticipantInvitationResult's own
 * `outcome` discriminator.
 */
export function createParticipantInvitation(
  client: ApiClient,
  productionId: string,
  fields: { email: string; participantType: string; remarks?: string | null }
): Promise<CreateParticipantInvitationResult> {
  return client.post<CreateParticipantInvitationResult>(`/productions/${productionId}/participant-invitations`, {
    email: fields.email,
    participant_type: fields.participantType,
    remarks: fields.remarks,
  });
}

export function fetchParticipantInvitations(client: ApiClient, productionId: string): Promise<ParticipantInvitation[]> {
  return client.get<ParticipantInvitation[]>(`/productions/${productionId}/participant-invitations`);
}

export function resendParticipantInvitation(client: ApiClient, invitationId: string): Promise<ParticipantInvitation> {
  return client.post<ParticipantInvitation>(`/participant-invitations/${invitationId}/resend`);
}

export function cancelParticipantInvitation(client: ApiClient, invitationId: string): Promise<ParticipantInvitation> {
  return client.post<ParticipantInvitation>(`/participant-invitations/${invitationId}/cancel`);
}

/**
 * §19/§23: unauthenticated - called from register.tsx when arriving via
 * an invitation link, before the invitee has any StageArt session.
 */
export function fetchParticipantInvitationPreview(token: string): Promise<ParticipantInvitationPreview> {
  return publicGet<ParticipantInvitationPreview>(`/participant-invitations/resolve?token=${encodeURIComponent(token)}`);
}
