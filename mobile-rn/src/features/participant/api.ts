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
