import type { ApiClient } from '@/api/client';
import type {
  CreateParticipantInvitationResult,
  ManagedParticipantInvitation,
  MyParticipantInvitation,
  ParticipantInvitation,
} from '@/types/api';

/**
 * An admin invites someone by 氏名＋メールアドレス＋役割＋備考
 * (invitation policy, docs/03-InvitationAndEnrollmentPolicy.md, 2026-11-27 -
 * the same way as an organization invitation):
 * - the address belongs to a verified account: that person is registered at
 *   once - `outcome` MEMBER_ADDED (no mail);
 * - otherwise an invitation is mailed - INVITATION_SENT, or INVITATION_RESENT
 *   when a PENDING one to the same address and type was resent. The person is
 *   registered once they verify that address (or sign in with Google with it).
 * Nobody accepts or rejects an invitation.
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

/** R14B-4R (§7.1): every invitation of the Production with effective_status, send status and limits. */
export function fetchParticipantInvitations(client: ApiClient, productionId: string): Promise<ManagedParticipantInvitation[]> {
  return client.get<ManagedParticipantInvitation[]>(`/productions/${productionId}/participant-invitations`);
}

export function resendParticipantInvitation(client: ApiClient, invitationId: string): Promise<ParticipantInvitation> {
  return client.post<ParticipantInvitation>(`/participant-invitations/${invitationId}/resend`);
}

/** §7.1: 200 (also again for an invitation the managers already cancelled); 409 for another ended state. */
export function cancelParticipantInvitation(client: ApiClient, invitationId: string): Promise<ParticipantInvitation> {
  return client.post<ParticipantInvitation>(`/participant-invitations/${invitationId}/cancel`);
}

/**
 * The invitation the mail's link opens (/participant-invitations/{id}),
 * after login: shown only when its address is one of the logged-in person's
 * verified addresses (otherwise 404, like a missing one).
 */
export function fetchMyParticipantInvitation(client: ApiClient, invitationId: string): Promise<MyParticipantInvitation> {
  return client.get<MyParticipantInvitation>(`/me/participant-invitations/${encodeURIComponent(invitationId)}`);
}
