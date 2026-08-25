import type { ApiClient } from '@/api/client';
import type { JoinKey, ResolvedJoinKey } from '@/types/api';

export function issueOrganizationJoinKey(client: ApiClient, organizationId: string): Promise<JoinKey> {
  return client.post<JoinKey>(`/organizations/${organizationId}/join-keys`);
}

export function issueProductionJoinKey(client: ApiClient, productionId: string): Promise<JoinKey> {
  return client.post<JoinKey>(`/productions/${productionId}/join-keys`);
}

export function fetchOrganizationJoinKeys(client: ApiClient, organizationId: string): Promise<JoinKey[]> {
  return client.get<JoinKey[]>(`/organizations/${organizationId}/join-keys`);
}

export function fetchProductionJoinKeys(client: ApiClient, productionId: string): Promise<JoinKey[]> {
  return client.get<JoinKey[]>(`/productions/${productionId}/join-keys`);
}

export function disableJoinKey(client: ApiClient, joinKeyId: string): Promise<JoinKey> {
  return client.post<JoinKey>(`/join-keys/${joinKeyId}/disable`);
}

/** Backend-side `POST /join-keys/resolve` requires a session (the Join
 * Key flow is only reachable from within the already-logged-in app -
 * see docs/03-InitialOnboardingAndJoinKey.md), so this goes through the
 * authenticated client. */
export function resolveJoinKey(client: ApiClient, code: string): Promise<ResolvedJoinKey> {
  return client.post<ResolvedJoinKey>('/join-keys/resolve', { code });
}
