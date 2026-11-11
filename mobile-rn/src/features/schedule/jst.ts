/**
 * PR #7 R11 (docs/20-RehearsalManagementScreen.md §24): schedule times are
 * Asia/Tokyo business times - a theatre's 10:00 is 10:00 on every device,
 * whatever timezone the device is set to. Before R11 the list screens
 * formatted with the device's local time (Date#getHours), so the same item
 * could show a different time than the timetable editor.
 *
 * Asia/Tokyo has had no daylight saving time since 1951, so it is a fixed
 * +09:00 offset; plain UTC arithmetic is exact and does not depend on the
 * device's Intl/timezone data.
 */
export const JST_OFFSET_MS = 9 * 60 * 60 * 1000;

/** `YYYY-MM-DD[T ]HH:MM[:SS[.fff]]` with no offset. */
const WITHOUT_OFFSET = /^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})(?::(\d{2})(?:\.\d+)?)?$/;

/**
 * Reads an API date-time. With an offset (`+09:00`, `Z`, ...) it is that
 * instant. Without one it is Asia/Tokyo wall-clock time (the Backend's
 * rule for offset-free values) - never the device's local time, which is
 * what `new Date("2026-08-20T10:00:00")` would give. Returns null for
 * anything that is not a date-time.
 */
export function parseApiDateTime(value: string | null | undefined): Date | null {
  if (!value) {
    return null;
  }
  const naive = WITHOUT_OFFSET.exec(value);
  if (naive) {
    const [, y, mo, d, h, mi, s] = naive;
    const date = new Date(Date.UTC(+y, +mo - 1, +d, +h, +mi, s ? +s : 0) - JST_OFFSET_MS);
    return Number.isNaN(date.getTime()) ? null : date;
  }
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

export type JstParts = {
  year: number;
  /** 1-12 */
  month: number;
  day: number;
  hour: number;
  minute: number;
  /** 0 (Sunday) - 6 */
  weekday: number;
};

/** The calendar fields of an instant as seen in Asia/Tokyo. */
export function toJstParts(date: Date): JstParts {
  const shifted = new Date(date.getTime() + JST_OFFSET_MS);
  return {
    year: shifted.getUTCFullYear(),
    month: shifted.getUTCMonth() + 1,
    day: shifted.getUTCDate(),
    hour: shifted.getUTCHours(),
    minute: shifted.getUTCMinutes(),
    weekday: shifted.getUTCDay(),
  };
}

const pad = (n: number) => String(n).padStart(2, '0');

/** "2026-08-20" - the Asia/Tokyo date of an instant. */
export function jstDateKey(date: Date): string {
  const p = toJstParts(date);
  return `${p.year}-${pad(p.month)}-${pad(p.day)}`;
}

/** "10:00" - the Asia/Tokyo time of an instant. */
export function jstTime(date: Date): string {
  const p = toJstParts(date);
  return `${pad(p.hour)}:${pad(p.minute)}`;
}

/**
 * An API date-time as the Asia/Tokyo date and time an editor shows:
 * "2026-08-20T01:00:00Z" -> { date: "2026-08-20", time: "10:00" }.
 * Empty strings for a missing or invalid value.
 */
export function splitJstDateTime(value: string | null | undefined): { date: string; time: string } {
  const date = parseApiDateTime(value);
  return date ? { date: jstDateKey(date), time: jstTime(date) } : { date: '', time: '' };
}
