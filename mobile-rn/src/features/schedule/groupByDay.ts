import type { TimetableItem } from '@/types/api';

export type ScheduleDay = { dayKey: string; date: Date; items: TimetableItem[] };

const WEEKDAYS_JA = ['日', '月', '火', '水', '木', '金', '土'];

/** §12: day-grouped vertical time-series, not a Gantt/horizontal-time
 * layout (that remains Print View's - Phase 4.5 - concern only). */
export function groupByDay(items: TimetableItem[]): ScheduleDay[] {
  const sorted = [...items].sort(
    (a, b) => new Date(a.start_date_time).getTime() - new Date(b.start_date_time).getTime()
  );

  const order: string[] = [];
  const byDay = new Map<string, TimetableItem[]>();

  for (const item of sorted) {
    const d = new Date(item.start_date_time);
    const dayKey = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

    if (!byDay.has(dayKey)) {
      byDay.set(dayKey, []);
      order.push(dayKey);
    }
    byDay.get(dayKey)!.push(item);
  }

  return order.map((dayKey) => {
    const dayItems = byDay.get(dayKey)!;
    return { dayKey, date: new Date(dayItems[0].start_date_time), items: dayItems };
  });
}

export function formatDayHeader(date: Date): string {
  return `${date.getFullYear()}/${date.getMonth() + 1}/${date.getDate()}（${WEEKDAYS_JA[date.getDay()]}）`;
}

export function formatTime(iso: string): string {
  const d = new Date(iso);
  return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
}
