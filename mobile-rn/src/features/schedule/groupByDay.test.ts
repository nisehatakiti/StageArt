import type { TimetableItem } from '@/types/api';

import { formatDayHeader, formatTime, groupByDay } from './groupByDay';

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

describe('groupByDay (§12: day-grouped vertical layout)', () => {
  it('groups items into their local calendar day, sorted chronologically within and across days', () => {
    const items = [
      item('c', '2026-08-18T21:00:00+09:00'),
      item('a', '2026-08-18T09:00:00+09:00'),
      item('b', '2026-08-19T08:00:00+09:00'),
    ];

    const days = groupByDay(items);

    expect(days).toHaveLength(2);
    expect(days[0].items.map((i) => i.id)).toEqual(['a', 'c']);
    expect(days[1].items.map((i) => i.id)).toEqual(['b']);
  });

  it('returns an empty array for an empty input (Empty State callers key off this)', () => {
    expect(groupByDay([])).toEqual([]);
  });
});

describe('formatDayHeader / formatTime', () => {
  it('formats a day header with the Japanese weekday', () => {
    // 2026-08-18 is a Tuesday.
    expect(formatDayHeader(new Date(2026, 7, 18))).toBe('2026/8/18（火）');
  });

  it('formats a time as HH:mm in local time', () => {
    expect(formatTime('2026-08-18T09:05:00+09:00')).toMatch(/^\d{2}:\d{2}$/);
  });
});
