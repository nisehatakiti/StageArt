import type { TimetableItem } from '@/types/api';

import { defaultScheduleRange } from './dateRange';
import { formatDayHeader, formatTime, groupByDay } from './groupByDay';
import { parseApiDateTime, splitJstDateTime } from './jst';

/**
 * PR #7 R11 (docs/20-RehearsalManagementScreen.md §24): schedule times are
 * shown in Asia/Tokyo on every device.
 *
 * Nothing here may pass only because the test machine runs in JST: the
 * expected values hold in any timezone, the last case fails if any helper
 * reads the device's local date/time fields, and R11 ran this file with
 * the process timezone set to Asia/Tokyo, UTC, America/New_York,
 * America/Los_Angeles and Pacific/Kiritimati (TZ at Jest start - Jest does
 * not apply a change to process.env.TZ made inside a test file).
 */

function item(id: string, start: string, end: string | null = null): TimetableItem {
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

describe('Asia/Tokyo schedule times', () => {
  it('shows a 10:00 JST item as 10:00 in the list and in the editor', () => {
    for (const apiValue of ['2026-08-20T10:00:00+09:00', '2026-08-20T01:00:00Z', '2026-08-19T20:00:00-05:00']) {
      expect(formatTime(apiValue)).toBe('10:00');
      expect(splitJstDateTime(apiValue)).toEqual({ date: '2026-08-20', time: '10:00' });
      expect(formatDayHeader(apiValue)).toBe('2026/8/20（木）');
    }
  });

  it('reads a value without an offset as Asia/Tokyo time, not device time', () => {
    expect(formatTime('2026-08-20T10:00:00')).toBe('10:00');
    expect(formatTime('2026-08-20 10:00')).toBe('10:00');
    expect(parseApiDateTime('2026-08-20T10:00:00')?.toISOString()).toBe('2026-08-20T01:00:00.000Z');
  });

  it('uses the Asia/Tokyo date across the UTC/JST day and year boundaries', () => {
    expect(splitJstDateTime('2026-12-31T15:00:00Z')).toEqual({ date: '2027-01-01', time: '00:00' });
    expect(formatDayHeader('2026-12-31T15:00:00Z')).toBe('2027/1/1（金）');
    expect(splitJstDateTime('2026-12-31T23:59:00+09:00')).toEqual({ date: '2026-12-31', time: '23:59' });
    expect(formatDayHeader('2026-12-31T23:59:00+09:00')).toBe('2026/12/31（木）');
  });

  it('groups items into Asia/Tokyo days', () => {
    const days = groupByDay([
      item('jst-0030-new-year', '2026-12-31T15:30:00Z'),
      item('jst-2330', '2026-12-31T23:30:00+09:00'),
      item('jst-1000-new-year', '2027-01-01T10:00:00+09:00'),
    ]);

    expect(days.map((day) => day.dayKey)).toEqual(['2026-12-31', '2027-01-01']);
    expect(days[0].items.map((i) => i.id)).toEqual(['jst-2330']);
    expect(days[1].items.map((i) => i.id)).toEqual(['jst-0030-new-year', 'jst-1000-new-year']);
    expect(formatDayHeader(days[1].date)).toBe('2027/1/1（金）');
  });

  it('treats an invalid value as no value instead of showing NaN', () => {
    expect(parseApiDateTime('not a date')).toBeNull();
    expect(parseApiDateTime('')).toBeNull();
    expect(parseApiDateTime(null)).toBeNull();
    expect(formatTime('not a date')).toBe('');
    expect(formatDayHeader('not a date')).toBe('');
    expect(splitJstDateTime('not a date')).toEqual({ date: '', time: '' });
  });

  it('builds 当日+翌日 from the Asia/Tokyo date', () => {
    // 2026-08-18 16:00 UTC is already 2026-08-19 01:00 in Tokyo.
    expect(defaultScheduleRange(new Date('2026-08-18T16:00:00Z'))).toEqual({
      from: '2026-08-19 00:00:00',
      to: '2026-08-21 00:00:00',
    });
    // Year end: 2026-12-31 14:59 UTC is 23:59 in Tokyo, still the 31st.
    expect(defaultScheduleRange(new Date('2026-12-31T14:59:00Z'))).toEqual({
      from: '2026-12-31 00:00:00',
      to: '2027-01-02 00:00:00',
    });
  });

  it("never reads the device's local date/time fields", () => {
    const localGetters = [
      'getFullYear',
      'getMonth',
      'getDate',
      'getDay',
      'getHours',
      'getMinutes',
      'getSeconds',
      'getTimezoneOffset',
    ] as const;
    const spies = localGetters.map((name) =>
      jest.spyOn(Date.prototype, name).mockImplementation(() => {
        throw new Error(`device-local Date#${name} used`);
      })
    );

    try {
      expect(formatTime('2026-08-20T01:00:00Z')).toBe('10:00');
      expect(formatTime('2026-08-20T10:00:00')).toBe('10:00');
      expect(formatDayHeader('2026-12-31T15:00:00Z')).toBe('2027/1/1（金）');
      expect(splitJstDateTime('2026-12-31T15:30:00Z')).toEqual({ date: '2027-01-01', time: '00:30' });
      expect(groupByDay([item('a', '2026-12-31T15:30:00Z')]).map((day) => day.dayKey)).toEqual(['2027-01-01']);
      expect(defaultScheduleRange(new Date('2026-08-18T16:00:00Z')).from).toBe('2026-08-19 00:00:00');
    } finally {
      spies.forEach((spy) => spy.mockRestore());
    }
  });
});
