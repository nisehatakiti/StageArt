import type { ApiClient } from '@/api/client';
import type { FavoriteStatus, MyFavorite } from '@/types/api';

export function addFavorite(client: ApiClient, targetType: 'ORGANIZATION' | 'PRODUCTION', targetId: string): Promise<FavoriteStatus> {
  return client.post<FavoriteStatus>('/favorites', { target_type: targetType, target_id: targetId });
}

/** DELETE /favorites?target_type=...&target_id=... - ApiClient.delete()
 * takes no body, and WP_REST_Request::get_param() already reads query
 * params the same way it reads a body, so the target is passed as a
 * query string here rather than extending the shared client just for
 * this one caller. */
export function removeFavorite(client: ApiClient, targetType: 'ORGANIZATION' | 'PRODUCTION', targetId: string): Promise<FavoriteStatus> {
  return client.delete<FavoriteStatus>(
    `/favorites?target_type=${encodeURIComponent(targetType)}&target_id=${encodeURIComponent(targetId)}`
  );
}

export function fetchMyFavorites(client: ApiClient): Promise<MyFavorite[]> {
  return client.get<MyFavorite[]>('/me/favorites');
}
