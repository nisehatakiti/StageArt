import { formatEndTime } from './groupByDay';
import { addDays, endDayLabel, rowDateTimeErrorMessage, rowDateTimes, rowFromItem, withStartDate, type RowTimes } from './timetableRowTimes';

/**
 * PR #7 R12 follow-up (docs/20-RehearsalManagementScreen.md §25.4): the
 * timetable editor keeps the start date and the end's own date, so items
 * longer than a day keep their end date when edited. The expected values
 * hold in any device timezone (run with TZ set to Asia/Tokyo, UTC,
 * America/New_York, America/Los_Angeles and Pacific/Kiritimati at Jest
 * start).
 */

/** What the API returns for a saved row (the Backend stores the instant
 * and returns it with "+09:00"), so a reload can be simulated. */
function reload(saved: { start: string; end: string | null }) {
  return rowFromItem(saved.start, saved.end);
}

function save(row: RowTimes, original?: ReturnType<typeof rowFromItem>) {
  const times = rowDateTimes(row, original);
  if (!times.ok) {
    throw new Error(`not saved: ${times.error}`);
  }
  return { start: times.start, end: times.end };
}

const cases: [string, string, string, string][] = [
  ['same day', '2026-11-10T10:00:00+09:00', '2026-11-10T12:00:00+09:00', ''],
  ['next day', '2026-11-10T23:30:00+09:00', '2026-11-11T00:30:00+09:00', '翌日'],
  ['two days later', '2026-11-10T09:00:00+09:00', '2026-11-12T18:00:00+09:00', '11/12終了'],
  ['three days later', '2026-11-10T09:00:00+09:00', '2026-11-13T09:00:00+09:00', '11/13終了'],
  ['a week across the year end', '2026-12-28T22:00:00+09:00', '2027-01-04T06:00:00+09:00', '1/4終了'],
];

describe('timetable editor rows: items of any length', () => {
  it.each(cases)('%s: loads the end date and saves the item back unchanged', (_, start, end, label) => {
    const original = rowFromItem(start, end);
    expect(original.endDate).toBe(end.slice(0, 10));
    expect(endDayLabel(original, original)).toBe(label);
    expect(save(original, original)).toEqual({ start, end });
  });

  it.each(cases)('%s: changing only the end time keeps the end date', (_, start, end) => {
    const original = rowFromItem(start, end);
    const edited = { ...original, endTime: '23:55' };
    expect(save(edited, original)).toEqual({ start, end: `${end.slice(0, 10)}T23:55:00+09:00` });
  });

  it('changing only the end date moves the end to that date', () => {
    const original = rowFromItem('2026-11-10T23:30:00+09:00', '2026-11-11T00:30:00+09:00');
    expect(save({ ...original, endDate: '2026-11-13' }, original)).toEqual({
      start: '2026-11-10T23:30:00+09:00',
      end: '2026-11-13T00:30:00+09:00',
    });
    expect(endDayLabel({ ...original, endDate: '2026-11-13' }, original)).toBe('11/13終了');
    // Back to the start date: 00:30 is then before 23:30 - refused, not moved.
    expect(rowDateTimes({ ...original, endDate: '2026-11-10' }, original)).toEqual({ ok: false, error: 'end-before-start' });
  });

  it('an end time before the start on the same end date is refused, never moved to another day', () => {
    const sameDay = rowFromItem('2026-11-10T22:00:00+09:00', '2026-11-10T23:00:00+09:00');
    expect(rowDateTimes({ ...sameDay, endTime: '01:30' }, sameDay)).toEqual({ ok: false, error: 'end-before-start' });
    expect(endDayLabel({ ...sameDay, endTime: '01:30' }, sameDay)).toBe('');
    // ...until the end date says the next day.
    expect(save({ ...sameDay, endDate: '2026-11-11', endTime: '01:30' }, sameDay)).toEqual({
      start: '2026-11-10T22:00:00+09:00',
      end: '2026-11-11T01:30:00+09:00',
    });

    const nextDay = rowFromItem('2026-11-10T23:30:00+09:00', '2026-11-11T00:30:00+09:00');
    expect(rowDateTimes({ ...nextDay, endTime: '23:30' }, nextDay)).toEqual({
      ok: true,
      start: '2026-11-10T23:30:00+09:00',
      end: '2026-11-11T23:30:00+09:00',
    });
    expect(rowDateTimes({ ...nextDay, endDate: '2026-11-10', endTime: '23:30' }, nextDay)).toEqual({ ok: false, error: 'same-time' });
  });

  it('changing the start date moves the end date by the same number of days', () => {
    const original = rowFromItem('2026-11-10T09:00:00+09:00', '2026-11-13T18:00:00+09:00');
    const moved = withStartDate(original, '2026-11-12');
    expect(moved).toMatchObject({ date: '2026-11-12', endDate: '2026-11-15' });
    expect(save(moved, original)).toEqual({ start: '2026-11-12T09:00:00+09:00', end: '2026-11-15T18:00:00+09:00' });

    const back = withStartDate(original, '2026-10-30');
    expect(back).toMatchObject({ date: '2026-10-30', endDate: '2026-11-02' });
    expect(withStartDate(rowFromItem('2026-12-31T23:30:00+09:00', '2027-01-01T00:30:00+09:00'), '2027-02-28')).toMatchObject({
      endDate: '2027-03-01',
    });

    // Changing only the start time keeps both dates.
    expect(save({ ...original, startTime: '10:00' }, original)).toEqual({
      start: '2026-11-10T10:00:00+09:00',
      end: '2026-11-13T18:00:00+09:00',
    });
  });

  it('a new row (no end date chosen) ends on the start date, or the next day for an earlier end time', () => {
    const row = { date: '2026-11-10', startTime: '23:30', endDate: '', endTime: '00:30' };
    expect(save(row)).toEqual({ start: '2026-11-10T23:30:00+09:00', end: '2026-11-11T00:30:00+09:00' });
    expect(withStartDate(row, '2026-11-20')).toEqual({ ...row, date: '2026-11-20' });
    expect(save({ ...row, endDate: '2026-11-12' })).toEqual({ start: '2026-11-10T23:30:00+09:00', end: '2026-11-12T00:30:00+09:00' });
  });

  it('an item without an end keeps having none, and a cleared end time saves none', () => {
    const noEnd = rowFromItem('2026-11-10T10:00:00+09:00', null);
    expect(noEnd.endDate).toBe('');
    expect(save(noEnd, noEnd)).toEqual({ start: '2026-11-10T10:00:00+09:00', end: null });

    const long = rowFromItem('2026-11-10T09:00:00+09:00', '2026-11-13T09:00:00+09:00');
    expect(save({ ...long, endTime: '' }, long)).toEqual({ start: '2026-11-10T09:00:00+09:00', end: null });
  });

  it('edit, save and reload repeatedly keeps the start and end', () => {
    let saved = { start: '2026-11-10T09:00:00+09:00', end: '2026-11-13T18:00:00+09:00' as string | null };
    for (let round = 0; round < 3; round++) {
      const loaded = reload(saved);
      saved = save(loaded, loaded); // unchanged
      expect(saved).toEqual({ start: '2026-11-10T09:00:00+09:00', end: '2026-11-13T18:00:00+09:00' });
    }
    // An edit of the end time, then three unchanged saves.
    const loaded = reload(saved);
    saved = save({ ...loaded, endTime: '20:00' }, loaded);
    for (let round = 0; round < 3; round++) {
      const again = reload(saved);
      saved = save(again, again);
    }
    expect(saved).toEqual({ start: '2026-11-10T09:00:00+09:00', end: '2026-11-13T20:00:00+09:00' });
    // An API value written in another offset reads as the same instant.
    expect(save(reload({ start: '2026-11-10T00:00:00Z', end: '2026-11-13T09:00:00Z' }), undefined)).toEqual({
      start: '2026-11-10T09:00:00+09:00',
      end: '2026-11-13T18:00:00+09:00',
    });
  });

  it('list and detail show the end date of a long item', () => {
    expect(formatEndTime('2026-11-10T09:00:00+09:00', '2026-11-12T18:00:00+09:00')).toBe('11/12 18:00');
    expect(formatEndTime('2026-11-10T09:00:00+09:00', '2026-11-13T09:00:00+09:00')).toBe('11/13 09:00');
  });

  it('adds calendar days and words the validation errors', () => {
    expect(addDays('2026-12-30', 2)).toBe('2027-01-01');
    expect(addDays('2028-03-01', -1)).toBe('2028-02-29');
    expect(addDays('not a date', 1)).toBe('not a date');
    expect(rowDateTimeErrorMessage(2, 'same-time')).toBe('2行目：開始と終了が同じ時刻です。終了の日付・時刻を変えるか、終了時刻を空欄にしてください。');
    expect(rowDateTimeErrorMessage(3, 'end-before-start')).toBe('3行目：終了が開始より前になっています。終了日または終了時刻を直してください。');
  });
});
