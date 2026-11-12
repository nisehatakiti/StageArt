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

/**
 * PR #7 R12: an item's end time, relative to its start (Asia/Tokyo):
 * "12:00" the same day, "翌00:30" the next day, "1/2 00:30" any later day.
 * Empty for an invalid value.
 */
export function formatEndTime(startIso: string, endIso: string): string {
  const start = parseApiDateTime(startIso);
  const end = parseApiDateTime(endIso);
  if (!start || !end) {
    return '';
  }
  const startDay = jstDateKey(start);
  const endDay = jstDateKey(end);
  if (endDay === startDay) {
    return jstTime(end);
  }
  if (endDay === jstDateKey(new Date(start.getTime() + 24 * 60 * 60 * 1000))) {
    return `翌${jstTime(end)}`;
  }
  const p = toJstParts(end);
  return `${p.month}/${p.day} ${jstTime(end)}`;
}
