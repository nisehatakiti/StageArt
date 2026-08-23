import { defaultScheduleRange, formatWallClock } from './dateRange';

describe('formatWallClock (§11/§37: offset-free wall-clock string, matching the Backend convention)', () => {
  it('formats without any timezone offset suffix', () => {
    const formatted = formatWallClock(new Date(2026, 7, 18, 9, 5, 0));
    expect(formatted).toBe('2026-08-18 09:05:00');
    expect(formatted).not.toMatch(/[+-]\d{2}:?\d{2}$/);
    expect(formatted).not.toMatch(/Z$/);
  });
});

describe('defaultScheduleRange (§9: 当日+翌日, matches the Flutter reference implementation)', () => {
  it('spans from local midnight today to local midnight two days later', () => {
    const now = new Date(2026, 7, 18, 15, 30, 0);
    const range = defaultScheduleRange(now);

    expect(range.from).toBe('2026-08-18 00:00:00');
    expect(range.to).toBe('2026-08-20 00:00:00');
  });
});
