import { parseApiDateTime, splitJstDateTime, toJstIso } from './jst';

/**
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §25): how the timetable
 * editor's rows map to full start/end date-times (Asia/Tokyo, "+09:00").
 *
 * A row has a start date and time and an optional end date and time.
 *
 * - The end date of an item loaded from the API is the end's own date
 *   (R12 follow-up): an item ending the next day, two days later or later
 *   still keeps that date. Changing only the end time never moves it -
 *   an end that then falls on or before the start is refused, not moved.
 * - Changing the start date moves an end date by the same number of days
 *   (the item keeps how many days it spans).
 * - A row with no end date (a new row, or an item that had no end) takes
 *   the start date, or the next day when the end time is earlier than the
 *   start time (23:30-00:30).
 * - The same start and end is not allowed (no zero-length items).
 *
 * A row the user did not change sends back exactly the start/end it was
 * loaded with.
 */
export type RowTimes = {
  date: string;
  startTime: string;
  /** '' (or absent): not chosen - see above. */
  endDate?: string;
  endTime: string;
};

export type RowOriginal = RowTimes & { endDate: string; start: string; end: string | null };

export type RowDateTimeError = 'same-time' | 'end-before-start';

export type RowDateTimes = { ok: true; start: string; end: string | null } | { ok: false; error: RowDateTimeError };

const DATE = /^(\d{4})-(\d{2})-(\d{2})$/;
const DAY_MS = 24 * 60 * 60 * 1000;

function dateToUtc(date: string): number | null {
  const match = DATE.exec(date);
  if (!match) {
    return null;
  }
  const time = Date.UTC(+match[1], +match[2] - 1, +match[3]);
  return Number.isNaN(time) ? null : time;
}

/** "2026-12-30" + 2 -> "2027-01-01" (calendar days; no timezone involved). */
export function addDays(date: string, days: number): string {
  const time = dateToUtc(date);
  return time === null ? date : new Date(time + days * DAY_MS).toISOString().slice(0, 10);
}

/** "2026-12-31" -> "2027-01-01" */
export function nextDate(date: string): string {
  return addDays(date, 1);
}

/** The editor's row values for a loaded item, keeping what was loaded. */
export function rowFromItem(start: string, end: string | null): RowOriginal {
  const s = splitJstDateTime(start);
  const e = splitJstDateTime(end);
  return {
    date: s.date,
    startTime: s.time,
    endDate: e.date,
    endTime: e.time,
    start: asJst(start),
    end: end === null ? null : asJst(end),
  };
}

/** The same instant written with "+09:00" (unchanged when unreadable). */
function asJst(value: string): string {
  const date = parseApiDateTime(value);
  return date ? toJstIso(date) : value;
}

/**
 * The row after its start date is changed: an end date moves by the same
 * number of days, so a 3-day item stays a 3-day item.
 */
export function withStartDate<T extends RowTimes>(row: T, date: string): T {
  const from = dateToUtc(row.date);
  const to = dateToUtc(date);
  if (!row.endDate || from === null || to === null) {
    return { ...row, date };
  }
  return { ...row, date, endDate: addDays(row.endDate, Math.round((to - from) / DAY_MS)) };
}

function unchanged(row: RowTimes, original: RowOriginal | undefined): original is RowOriginal {
  return (
    original !== undefined &&
    row.date === original.date &&
    row.startTime === original.startTime &&
    (row.endDate ?? '') === original.endDate &&
    row.endTime === original.endTime
  );
}

/** The end date a row is saved with (see the rules above). */
function endDateOf(row: RowTimes): string {
  if (row.endDate) {
    return row.endDate;
  }
  return row.endTime < row.startTime ? nextDate(row.date) : row.date;
}

/** The start/end date-times (Asia/Tokyo, "+09:00") a row is saved with. */
export function rowDateTimes(row: RowTimes, original?: RowOriginal): RowDateTimes {
  if (unchanged(row, original)) {
    // An item saved before R12 with the same start and end: stop here,
    // before the editor starts replacing the DRAFT's items (the Backend
    // would reject it with 422 part-way through the save).
    if (original.end !== null && Date.parse(original.end) === Date.parse(original.start)) {
      return { ok: false, error: 'same-time' };
    }
    return { ok: true, start: original.start, end: original.end };
  }
  const start = `${row.date}T${row.startTime}:00+09:00`;
  if (!row.endTime) {
    return { ok: true, start, end: null };
  }
  const end = `${endDateOf(row)}T${row.endTime}:00+09:00`;
  // Both are "YYYY-MM-DDTHH:MM:00+09:00", so text order is time order.
  if (end === start) {
    return { ok: false, error: 'same-time' };
  }
  if (end < start) {
    return { ok: false, error: 'end-before-start' };
  }
  return { ok: true, start, end };
}

/** The validation message for a row the editor will not save. */
export function rowDateTimeErrorMessage(rowNumber: number, error: RowDateTimeError): string {
  return error === 'same-time'
    ? `${rowNumber}行目：開始と終了が同じ時刻です。終了の日付・時刻を変えるか、終了時刻を空欄にしてください。`
    : `${rowNumber}行目：終了が開始より前になっています。終了日または終了時刻を直してください。`;
}

/**
 * What the editor shows next to the end time: "翌日" when the item ends
 * the next day, "M/D終了" when it ends later than that, nothing when it
 * ends the same day (or the row cannot be saved as it is).
 */
export function endDayLabel(row: RowTimes, original?: RowOriginal): string {
  const times = rowDateTimes(row, original);
  if (!times.ok || !times.end) {
    return '';
  }
  const startDate = splitJstDateTime(times.start).date;
  const endDate = splitJstDateTime(times.end).date;
  if (endDate === startDate) {
    return '';
  }
  if (endDate === nextDate(startDate)) {
    return '翌日';
  }
  const [, m, d] = endDate.split('-').map(Number);
  return `${m}/${d}終了`;
}
