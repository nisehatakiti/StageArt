import type { NotificationFact } from '@/types/api';

export type NotificationViewModel = {
  id: string;
  title: string;
  summary: string | null;
  publishedAtDisplay: string;
  isRead: boolean;
};

/**
 * `type` is a discriminator the Backend added for future Notification
 * kinds (see NotificationResult.php's docblock) - today it only ever
 * sends 'timetable_version_published'. The label below is a direct,
 * literal description of that one known value, not an invented one; an
 * unrecognized future `type` falls back to the raw string rather than a
 * fabricated label.
 */
const TITLE_BY_TYPE: Record<string, string> = {
  timetable_version_published: 'タイムテーブルが更新されました',
};

/**
 * §23: `published_by` is a Person ID with no Person Display Name API to
 * resolve it against, so it is deliberately not surfaced here - showing
 * a raw UUID would not be meaningful to a user, and no better label may
 * be invented. Only fields with real display value are converted.
 */
export function buildNotificationViewModel(notification: NotificationFact): NotificationViewModel {
  return {
    id: notification.id,
    title: TITLE_BY_TYPE[notification.type] ?? notification.type,
    summary: notification.change_summary,
    publishedAtDisplay: formatPublishedAt(notification.published_at),
    isRead: notification.is_read,
  };
}

function formatPublishedAt(iso: string): string {
  const d = new Date(iso);

  if (Number.isNaN(d.getTime())) {
    return iso;
  }

  const date = `${d.getFullYear()}/${d.getMonth() + 1}/${d.getDate()}`;
  const time = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;

  return `${date} ${time}`;
}
