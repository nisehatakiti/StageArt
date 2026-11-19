import type { Href } from 'expo-router';

import type { MyNotification } from '@/types/api';

/**
 * Where tapping a My Notification takes the user, decided only from the
 * notification's structured fields (never by parsing `message`):
 *
 * - a Rehearsal notification (`rehearsal_id` + `production_id`) opens
 *   that Rehearsal's attendance screen;
 * - 修正8: a notification whose generic target is an Expense
 *   (`target_type: 'expense'`) opens that Expense's detail screen, where
 *   an approver approves / rejects it and the applicant sees the outcome.
 *
 * Returns null when the notification points nowhere specific; each
 * caller keeps its own fallback for that case.
 */
export function resolveNotificationRoute(notification: Pick<MyNotification, 'rehearsal_id' | 'production_id' | 'target_type' | 'target_id'>): Href | null {
  if (notification.rehearsal_id && notification.production_id) {
    return `/production/${notification.production_id}/schedule/attendance/${notification.rehearsal_id}` as Href;
  }

  if (notification.target_type === 'expense' && notification.target_id) {
    return `/expenses/${notification.target_id}` as Href;
  }

  return null;
}
