import type { ApiClient } from '@/api/client';
import type { Participant } from '@/types/api';

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
 * StageArt メンバー管理: 既存Personをメンバーに追加 (担当者権限をメンバー管理
 * へ統合・整理 §2-A instruction) - subject_type: PERSON, already fully
 * supported server-side (CreateParticipantUseCase.php's assertSubjectExists()
 * validates the Person exists) but never wired up in mobile-rn before.
 */
export function createPersonParticipant(
  client: ApiClient,
  productionId: string,
  fields: { personId: string; participantType: string; remarks?: string | null }
): Promise<Participant> {
  return client.post<Participant>(`/productions/${productionId}/participants`, {
    subject_type: 'PERSON',
    subject_id: fields.personId,
    participant_type: fields.participantType,
    remarks: fields.remarks,
  });
}

export function updateParticipant(
  client: ApiClient,
  participantId: string,
  fields: { participantType: string; status: string; remarks?: string | null }
): Promise<Participant> {
  return client.put<Participant>(`/participants/${participantId}`, {
    participant_type: fields.participantType,
    status: fields.status,
    remarks: fields.remarks,
  });
}
