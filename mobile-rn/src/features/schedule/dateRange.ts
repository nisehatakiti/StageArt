import { JST_OFFSET_MS, toJstParts } from './jst';

/**
 * §11/§37: an offset-free "YYYY-MM-DD HH:mm:ss" string built from the
 * Date's own (device-local) components.
 *
 * PR #7 R11: the Backend reads an offset-free from/to as Asia/Tokyo
 * wall-clock time (TimetableItemDateTime), so defaultScheduleRange() no
 * longer uses this - it builds the Asia/Tokyo day itself.
 */
export function formatWallClock(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(
    date.getMinutes()
  )}:${pad(date.getSeconds())}`;
}

export type ScheduleRange = { from: string; to: string } | undefined;

/**
 * §9: "当日+翌日" default view, matching the Flutter reference
 * implementation's convention exactly (schedule_screen.dart: `from =
 * today 00:00`, `to = from + 2 days`) - a half-open window covering
 * today and tomorrow in full.
 *
 * PR #7 R11: "today" is today in Asia/Tokyo, whatever the device's
 * timezone, and the bounds are Asia/Tokyo wall-clock values (no offset),
 * which the Backend reads as Asia/Tokyo time.
 */
export function defaultScheduleRange(now: Date = new Date()): { from: string; to: string } {
  const today = toJstParts(now);
  const start = new Date(Date.UTC(today.year, today.month - 1, today.day) - JST_OFFSET_MS);
  const end = new Date(Date.UTC(today.year, today.month - 1, today.day + 2) - JST_OFFSET_MS);

  return { from: jstWallClock(start), to: jstWallClock(end) };
}

function jstWallClock(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0');
  const p = toJstParts(date);
  return `${p.year}-${pad(p.month)}-${pad(p.day)} ${pad(p.hour)}:${pad(p.minute)}:00`;
}
