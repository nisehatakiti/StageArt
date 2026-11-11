import type { TimetableItem } from '@/types/api';

import { jstDateKey, jstTime, parseApiDateTime, toJstParts } from './jst';

export type ScheduleDay = { dayKey: string; date: Date; items: TimetableItem[] };

const WEEKDAYS_JA = ['日', '月', '火', '水', '木', '金', '土'];

const instant = (value: string) => parseApiDateTime(value)?.getTime() ?? 0;

/** §12: day-grouped vertical time-series, not a Gantt/horizontal-time
 * layout (that remains Print View's - Phase 4.5 - concern only).
 * PR #7 R11: days are Asia/Tokyo days (./jst.ts), not the device's. */
export function groupByDay(items: TimetableItem[]): ScheduleDay[] {
  const sorted = [...items].sort((a, b) => instant(a.start_date_time) - instant(b.start_date_time));

  const order: string[] = [];
  const byDay = new Map<string, TimetableItem[]>();

  for (const item of sorted) {
    const dayKey = jstDateKey(new Date(instant(item.start_date_time)));

    if (!byDay.has(dayKey)) {
      byDay.set(dayKey, []);
      order.push(dayKey);
    }
    byDay.get(dayKey)!.push(item);
  }

  return order.map((dayKey) => {
    const dayItems = byDay.get(dayKey)!;
    return { dayKey, date: new Date(instant(dayItems[0].start_date_time)), items: dayItems };
  });
}

/** "2026/8/18（火）" - the Asia/Tokyo date (PR #7 R11). A string is read
 * as an API date-time (parseApiDateTime). Empty for an invalid value. */
export function formatDayHeader(date: Date | string): string {
  const value = typeof date === 'string' ? parseApiDateTime(date) : date;
  if (!value || Number.isNaN(value.getTime())) {
    return '';
  }
  const p = toJstParts(value);
  return `${p.year}/${p.month}/${p.day}（${WEEKDAYS_JA[p.weekday]}）`;
}

/** "10:00" - the Asia/Tokyo time of an API date-time, on every device
 * (PR #7 R11). Empty for an invalid value. */
export function formatTime(iso: string): string {
  const value = parseApiDateTime(iso);
  return value ? jstTime(value) : '';
}
