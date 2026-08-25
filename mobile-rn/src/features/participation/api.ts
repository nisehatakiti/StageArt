import type { ApiClient } from '@/api/client';
import type { ParticipationRequest } from '@/types/api';

/** Exactly one of `productionId`/`joinKeyCode` should be set - mirrors
 * requestOrganizationMembership()'s same dual entry point at the
 * Production Scope. `participantType` is 'CAST' | 'STAFF', chosen by the
 * requester before confirming (docs/03-InitialOnboardingAndJoinKey.md §11). */
export function requestProductionParticipation(
  client: ApiClient,
  fields: { productionId?: string; joinKeyCode?: string; participantType: string }
): Promise<ParticipationRequest> {
  return client.post<ParticipationRequest>('/participation-requests', {
    production_id: fields.productionId,
    join_key_code: fields.joinKeyCode,
    participant_type: fields.participantType,
  });
}

export function approveParticipationRequest(client: ApiClient, participantId: string): Promise<ParticipationRequest> {
  return client.post<ParticipationRequest>(`/participation-requests/${participantId}/approve`);
}

export function rejectParticipationRequest(client: ApiClient, participantId: string): Promise<ParticipationRequest> {
  return client.post<ParticipationRequest>(`/participation-requests/${participantId}/reject`);
}

export function fetchPendingParticipationRequests(client: ApiClient, productionId: string): Promise<ParticipationRequest[]> {
  return client.get<ParticipationRequest[]>(`/productions/${productionId}/participation-requests`);
}
