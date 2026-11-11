import { parseApiDateTime, splitJstDateTime, toJstIso } from './jst';

/**
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §25): how the timetable
 * editor's one-date rows map to full start/end date-times.
 *
 * A row has the item's date (its START date - the list groups it there),
 * a start time and an optional end time. An end time earlier than the
 * start time means the item ends the next day (23:30-00:30). The same
 * start and end time is not allowed (no zero-length items).
 *
 * A row the user did not change sends back exactly the start/end it was
 * loaded with, so saving never loses or moves an end date - including an
 * item that runs longer than a day, which one date per row cannot show.
 */
export type RowTimes = { date: string; startTime: string; endTime: string };

export type RowOriginal = RowTimes & { start: string; end: string | null };

export type RowDateTimes =
  | { ok: true; start: string; end: string | null }
  | { ok: false; error: 'same-time' };

/** "2026-12-31" -> "2027-01-01" */
export function nextDate(date: string): string {
  const [y, m, d] = date.split('-').map(Number);
  const next = new Date(Date.UTC(y, m - 1, d + 1));
  return next.toISOString().slice(0, 10);
}

/** The editor's row values for a loaded item, keeping what was loaded. */
export function rowFromItem(start: string, end: string | null): RowOriginal {
  const s = splitJstDateTime(start);
  const e = splitJstDateTime(end);
  return { date: s.date, startTime: s.time, endTime: e.time, start: asJst(start), end: end === null ? null : asJst(end) };
}

/** The same instant written with "+09:00" (unchanged when unreadable). */
function asJst(value: string): string {
  const date = parseApiDateTime(value);
  return date ? toJstIso(date) : value;
}

function unchanged(row: RowTimes, original: RowOriginal | undefined): original is RowOriginal {
  return (
    original !== undefined &&
    row.date === original.date &&
    row.startTime === original.startTime &&
    row.endTime === original.endTime
  );
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
  if (row.endTime === row.startTime) {
    return { ok: false, error: 'same-time' };
  }
  const endDate = row.endTime > row.startTime ? row.date : nextDate(row.date);
  return { ok: true, start, end: `${endDate}T${row.endTime}:00+09:00` };
}

/**
 * What the editor shows next to the end time: "翌日" when the item ends
 * the next day, "M/D終了" when it ends later than that (only possible for
 * an item created through the API), nothing when it ends the same day.
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
