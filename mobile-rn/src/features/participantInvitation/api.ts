import type { ApiClient } from '@/api/client';
import { publicGet } from '@/api/publicClient';
import type { CreateParticipantInvitationResult, ParticipantInvitation, ParticipantInvitationPreview } from '@/types/api';

/**
 * StageArt Productionメンバー追加(氏名＋メールアドレス統一)ラウンド §1/§2:
 * the single, unified member-add action - an admin always submits
 * 氏名＋メールアドレス＋役割＋備考 together. One call handles all three
 * outcomes - an email that already belongs to an existing StageArt
 * Person is added directly as a Participant (the submitted name is not
 * used in that case), an unregistered email becomes a
 * ParticipantInvitation carrying the submitted name, or resends an
 * existing usable one - see CreateParticipantInvitationResult's own
 * `outcome` discriminator.
 */
export function createParticipantInvitation(
  client: ApiClient,
  productionId: string,
  fields: { name: string; email: string; participantType: string; remarks?: string | null }
): Promise<CreateParticipantInvitationResult> {
  return client.post<CreateParticipantInvitationResult>(`/productions/${productionId}/participant-invitations`, {
    name: fields.name,
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
