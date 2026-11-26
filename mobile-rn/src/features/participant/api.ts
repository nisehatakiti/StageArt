import type { ApiClient } from '@/api/client';
import type { Participant, ParticipatingProduction, WithdrawParticipationResult } from '@/types/api';

/**
 * StageArt Web版 公演管理 Phase: no `features/participant/` layer
 * existed before this Phase - only `features/participation/` (the
 * Person-initiated request/approve/reject flow, itself backed by the
 * exact same Participant rows at PENDING status - see
 * RequestProductionParticipationUseCase.php). This is the direct
 * roster API (ParticipantRestController.php, confirmed already fully
 * implemented server-side): list every Participant a Production
 * actually has, and remove one.
 */
export function fetchParticipants(client: ApiClient, productionId: string): Promise<Participant[]> {
  return client.get<Participant[]>(`/productions/${productionId}/participants`);
}

/**
 * GET /me/participating-productions: the caller's own ACTIVE PERSON
 * Participant rows, each joined to its Production
 * (ListMyParticipatingProductionsUseCase.php) - the formal data source
 * for 「参加している公演・活動」, replacing the old upcoming_rehearsals-based
 * proxy (see participating-productions.tsx).
 */
export function fetchMyParticipatingProductions(client: ApiClient): Promise<ParticipatingProduction[]> {
  return client.get<ParticipatingProduction[]>('/me/participating-productions');
}

export function cancelParticipant(client: ApiClient, participantId: string): Promise<void> {
  return client.delete<void>(`/participants/${participantId}`);
}

/**
 * StageArt Phase 1 (docs/21-MemberManagementScreen.md §2.4): adds a
 * member by name only - no StageArt account required
 * (subject_type: NAME_ONLY, see CreateParticipantUseCase.php).
 */
export function createNameOnlyParticipant(
  client: ApiClient,
  productionId: string,
  fields: { displayName: string; participantType: string; remarks?: string | null }
): Promise<Participant> {
  return client.post<Participant>(`/productions/${productionId}/participants`, {
    subject_type: 'NAME_ONLY',
    display_name: fields.displayName,
    participant_type: fields.participantType,
    remarks: fields.remarks,
  });
}

/**
 * PR #7 R14-B (design doc §5.2 / §10, N6-N8): the role and remarks only.
 * `status` is never sent (the state is changed by its own operations:
 * invitation / withdrawal / deletion); `remarks: null` clears the remarks.
 * A PERSON or ORGANIZATION cannot be added directly any more (D10) - only
 * through an invitation the person accepts.
 */
export function updateParticipant(
  client: ApiClient,
  participantId: string,
  fields: { participantType: string; remarks: string | null }
): Promise<Participant> {
  return client.put<Participant>(`/participants/${participantId}`, {
    participant_type: fields.participantType,
    remarks: fields.remarks,
  });
}

/**
 * R14-B §7.2: the logged-in Person leaves the Production themselves (own
 * ACTIVE PERSON row only). Idempotent: already WITHDRAWN answers the same.
 * 409 withdraw_primary_manager / withdraw_not_allowed, 403 for someone
 * else's row. Their delegate roles in the Production end with it.
 */
export function withdrawParticipation(client: ApiClient, participantId: string): Promise<WithdrawParticipationResult> {
  return client.post<WithdrawParticipationResult>(`/participants/${participantId}/withdraw`);
}
