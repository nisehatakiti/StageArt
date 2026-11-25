import type { ApiClient } from '@/api/client';
import type {
  AcceptParticipantInvitationResult,
  CreateParticipantInvitationResult,
  ManagedParticipantInvitation,
  MyParticipantInvitation,
  ParticipantInvitation,
  RejectParticipantInvitationResult,
} from '@/types/api';

/**
 * PR #7 R14-B (design doc §7.1, stage 4): an admin invites someone by
 * 氏名＋メールアドレス＋役割＋備考. It is always an invitation - the address
 * is never looked up and nobody is added directly: `outcome` is
 * INVITATION_SENT (a new invitation) or INVITATION_RESENT (a PENDING one to
 * the same address and type was resent). The person becomes a member only
 * when they accept it themselves.
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
 * R14-B §7.2: the invitations to the logged-in Person's verified addresses -
 * the only way to look an invitation up (no token, no lookup by id).
 */
export function fetchMyParticipantInvitations(client: ApiClient): Promise<MyParticipantInvitation[]> {
  return client.get<MyParticipantInvitation[]>('/me/participant-invitations');
}

export function acceptParticipantInvitation(client: ApiClient, invitationId: string): Promise<AcceptParticipantInvitationResult> {
  return client.post<AcceptParticipantInvitationResult>(`/participant-invitations/${invitationId}/accept`);
}

export function rejectParticipantInvitation(client: ApiClient, invitationId: string): Promise<RejectParticipantInvitationResult> {
  return client.post<RejectParticipantInvitationResult>(`/participant-invitations/${invitationId}/reject`);
}
