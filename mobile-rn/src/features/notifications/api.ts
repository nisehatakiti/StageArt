import type { ApiClient } from '@/api/client';
import type { MyNotification, NotificationFact } from '@/types/api';

/**
 * GET /productions/{id}/notifications (Backend Phase 3.5's
 * ListNotificationsForProductionUseCase). Returns every
 * TimetableVersionPublished Fact for the Production, newest first - this
 * is the only Notification source that exists server-side today (no
 * generic Notification Domain, no read/unread state, no per-recipient
 * rows - see NotificationFact's own docblock in src/types/api.ts). This
 * client renders exactly what comes back, nothing more.
 */
export function fetchProductionNotifications(client: ApiClient, productionId: string): Promise<NotificationFact[]> {
  return client.get<NotificationFact[]>(`/productions/${productionId}/notifications`);
}

/**
 * PATCH /notifications/{id}/read (Backend Phase 7.0). Idempotent on the
 * Backend side - calling this on an already-read Notification just
 * returns the same `is_read: true` result, so this Client never needs
 * to guard against double-calling it.
 */
export function markNotificationRead(client: ApiClient, notificationId: string): Promise<{ id: string; is_read: boolean }> {
  return client.patch<{ id: string; is_read: boolean }>(`/notifications/${notificationId}/read`);
}

/**
 * GET /me/notifications (Notification基盤実装 phase). The caller's own
 * personal Notification feed (Rehearsal Cancel/Reminder today) - a
 * separate id namespace and endpoint from `fetchProductionNotifications`
 * above, since this Fact type is per-recipient, not Production-shared.
 */
export function fetchMyNotifications(client: ApiClient): Promise<MyNotification[]> {
  return client.get<MyNotification[]>('/me/notifications');
}

/** PATCH /me/notifications/{id}/read. Idempotent on the Backend side,
 * same as `markNotificationRead` above. */
export function markMyNotificationRead(client: ApiClient, notificationId: string): Promise<{ id: string; is_read: boolean }> {
  return client.patch<{ id: string; is_read: boolean }>(`/me/notifications/${notificationId}/read`);
}
