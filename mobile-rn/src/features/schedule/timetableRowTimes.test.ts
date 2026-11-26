import type { TimetableItem } from '@/types/api';

import { formatEndTime, groupByDay } from './groupByDay';
import { endDayLabel, nextDate, rowDateTimes, rowFromItem } from './timetableRowTimes';

/**
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §25): items that cross
 * midnight. The expected values hold in any device timezone; R12 ran this
 * file with TZ set to Asia/Tokyo, UTC, America/New_York,
 * America/Los_Angeles and Pacific/Kiritimati at Jest start.
 */
function item(id: string, start: string, end: string | null): TimetableItem {
  return {
    id,
    timetable_id: 'tt-1',
    title: id,
    description: null,
    start_date_time: start,
    end_date_time: end,
    display_order: null,
    category: null,
    venue: null,
    participant_type: null,
    target_person_ids: [],
    notes: null,
    created_at: '',
    updated_at: '',
  };
}

describe('timetable editor rows', () => {
  it('saves a same-day item on its date', () => {
    expect(rowDateTimes({ date: '2026-11-10', startTime: '10:00', endTime: '12:00' })).toEqual({
      ok: true,
      start: '2026-11-10T10:00:00+09:00',
      end: '2026-11-10T12:00:00+09:00',
    });
  });

  it('saves an end time before the start time on the next day', () => {
    expect(rowDateTimes({ date: '2026-11-10', startTime: '23:30', endTime: '00:30' })).toEqual({
      ok: true,
      start: '2026-11-10T23:30:00+09:00',
      end: '2026-11-11T00:30:00+09:00',
    });
    expect(rowDateTimes({ date: '2026-11-10', startTime: '23:30', endTime: '01:30' })).toEqual({
      ok: true,
      start: '2026-11-10T23:30:00+09:00',
      end: '2026-11-11T01:30:00+09:00',
    });
    expect(rowDateTimes({ date: '2026-12-31', startTime: '23:30', endTime: '00:30' })).toMatchObject({
      end: '2027-01-01T00:30:00+09:00',
    });
  });

  it('rejects the same start and end time and allows no end', () => {
    expect(rowDateTimes({ date: '2026-11-10', startTime: '23:30', endTime: '23:30' })).toEqual({ ok: false, error: 'same-time' });
    expect(rowDateTimes({ date: '2026-11-10', startTime: '23:30', endTime: '' })).toEqual({
      ok: true,
      start: '2026-11-10T23:30:00+09:00',
      end: null,
    });
  });

  it('loads an item crossing midnight on its start date with 翌日, and saves it back unchanged', () => {
    for (const end of ['2026-11-11T00:30:00+09:00', '2026-11-10T15:30:00Z']) {
      const original = rowFromItem('2026-11-10T23:30:00+09:00', end);
      expect(original).toMatchObject({ date: '2026-11-10', startTime: '23:30', endTime: '00:30' });
      expect(endDayLabel(original, original)).toBe('翌日');
      expect(rowDateTimes(original, original)).toEqual({
        ok: true,
        start: '2026-11-10T23:30:00+09:00',
        end: '2026-11-11T00:30:00+09:00',
      });
    }
  });

  // R12 follow-up: this test first expected an edited end time to be
  // recomputed onto the next day (end 11/11 01:30), which lost the item's
  // 11/12 end date. The end date is now kept (timetableRowTimesMultiDay.test.ts).
  it('keeps an end more than a day later when the row is not changed, and when only its end time is changed', () => {
    const original = rowFromItem('2026-11-10T23:30:00+09:00', '2026-11-12T09:00:00+09:00');
    expect(original.endDate).toBe('2026-11-12');
    expect(endDayLabel(original, original)).toBe('11/12終了');
    expect(rowDateTimes(original, original)).toMatchObject({ end: '2026-11-12T09:00:00+09:00' });

    const edited = { ...original, endTime: '01:30' };
    expect(rowDateTimes(edited, original)).toMatchObject({ end: '2026-11-12T01:30:00+09:00' });
    expect(endDayLabel(edited, original)).toBe('11/12終了');
  });

  it('stops an unchanged pre-R12 item that has the same start and end before saving', () => {
    const original = rowFromItem('2026-11-10T10:00:00+09:00', '2026-11-10T01:00:00Z');
    expect(rowDateTimes(original, original)).toEqual({ ok: false, error: 'same-time' });
    expect(rowDateTimes({ ...original, endTime: '' }, original)).toEqual({ ok: true, start: '2026-11-10T10:00:00+09:00', end: null });
  });

  it('shows no label for a same-day item and computes the next date across month and year ends', () => {
    expect(endDayLabel({ date: '2026-11-10', startTime: '10:00', endTime: '12:00' })).toBe('');
    expect(nextDate('2026-11-30')).toBe('2026-12-01');
    expect(nextDate('2026-12-31')).toBe('2027-01-01');
    expect(nextDate('2028-02-28')).toBe('2028-02-29');
  });
});

describe('list and detail display', () => {
  it('shows the end time relative to the start day', () => {
    expect(formatEndTime('2026-11-10T10:00:00+09:00', '2026-11-10T12:00:00+09:00')).toBe('12:00');
    expect(formatEndTime('2026-11-10T23:30:00+09:00', '2026-11-11T00:30:00+09:00')).toBe('翌00:30');
    expect(formatEndTime('2026-11-10T23:30:00+09:00', '2026-11-11T01:30:00+09:00')).toBe('翌01:30');
    expect(formatEndTime('2026-11-10T23:30:00+09:00', '2026-11-10T15:30:00Z')).toBe('翌00:30');
    expect(formatEndTime('2026-12-31T23:30:00+09:00', '2027-01-01T00:30:00+09:00')).toBe('翌00:30');
    expect(formatEndTime('2026-11-10T23:30:00+09:00', '2026-11-12T09:00:00+09:00')).toBe('11/12 09:00');
    expect(formatEndTime('2026-11-10T23:30:00+09:00', 'not a date')).toBe('');
  });

  it('groups an item crossing midnight under its start day', () => {
    const days = groupByDay([
      item('late', '2026-11-10T23:30:00+09:00', '2026-11-11T00:30:00+09:00'),
      item('next-morning', '2026-11-11T09:00:00+09:00', '2026-11-11T10:00:00+09:00'),
    ]);
    expect(days.map((day) => [day.dayKey, day.items.map((i) => i.id)])).toEqual([
      ['2026-11-10', ['late']],
      ['2026-11-11', ['next-morning']],
    ]);
  });
});
