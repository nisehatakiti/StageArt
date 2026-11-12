import type { ApiClient } from '@/api/client';
import { publicPost } from '@/api/publicClient';
import type { NotificationEmailSettings, RequestNotificationEmailChangeResult } from '@/types/api';

/** GET /me/notification-email - always resolved from the requester's own
 * identity server-side (no {id} parameter exists on this route). */
export function fetchNotificationEmailSettings(client: ApiClient): Promise<NotificationEmailSettings> {
  return client.get<NotificationEmailSettings>('/me/notification-email');
}

/** POST /me/notification-email/change-request - only ever creates a
 * pending verification; the current notification email is unchanged
 * until the confirmation link is opened (see verifyNotificationEmailChange). */
export function requestNotificationEmailChange(client: ApiClient, email: string): Promise<RequestNotificationEmailChangeResult> {
  return client.post<RequestNotificationEmailChangeResult>('/me/notification-email/change-request', { email });
}

/** POST /notification-email/verify - public (no session required), since
 * the confirmation link may be opened on a different device/session than
 * the one that requested the change - mirrors verifyEmail() in
 * features/auth/api.ts. */
export function verifyNotificationEmailChange(token: string): Promise<{ success: boolean }> {
  return publicPost<{ success: boolean }>('/notification-email/verify', { token });
}
