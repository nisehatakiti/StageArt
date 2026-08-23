import type { ApiClient } from '@/api/client';
import type { PushPreference } from '@/types/api';

/** GET /me/push-preference - always resolved from the requester's own
 * identity server-side (no {id} parameter exists on this route). */
export function fetchPushPreference(client: ApiClient): Promise<PushPreference> {
  return client.get<PushPreference>('/me/push-preference');
}

/** PUT /me/push-preference */
export function updatePushPreference(client: ApiClient, enabled: boolean): Promise<PushPreference> {
  return client.put<PushPreference>('/me/push-preference', { enabled });
}
