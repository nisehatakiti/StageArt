import { ACTUAL_STATUS_OPTIONS, attendanceSummary, responseOptionsForPhase, statusLabel } from './viewModel';

describe('statusLabel', () => {
  it('maps every known RehearsalAttendanceStatus value to a Japanese label', () => {
    expect(statusLabel('UNANSWERED')).toBe('未回答');
    expect(statusLabel('AVAILABLE')).toBe('参加可能');
    expect(statusLabel('ATTENDING')).toBe('出席');
    expect(statusLabel('ABSENT')).toBe('欠席（実績）');
  });

  it('falls back to the raw value for an unrecognized status', () => {
    expect(statusLabel('SOME_FUTURE_STATUS')).toBe('SOME_FUTURE_STATUS');
  });
});

describe('responseOptionsForPhase', () => {
  it('offers AVAILABLE/UNAVAILABLE for SCHEDULE_ADJUSTMENT', () => {
    expect(responseOptionsForPhase('SCHEDULE_ADJUSTMENT')).toEqual(['AVAILABLE', 'UNAVAILABLE']);
  });

  it('offers ATTENDING/NOT_ATTENDING for ATTENDANCE_CONFIRMATION', () => {
    expect(responseOptionsForPhase('ATTENDANCE_CONFIRMATION')).toEqual(['ATTENDING', 'NOT_ATTENDING']);
  });
});

describe('ACTUAL_STATUS_OPTIONS', () => {
  it('is exactly ATTENDED/LATE/EARLY_LEFT/ABSENT', () => {
    expect(ACTUAL_STATUS_OPTIONS).toEqual(['ATTENDED', 'LATE', 'EARLY_LEFT', 'ABSENT']);
  });
});

describe('attendanceSummary', () => {
  it('counts ATTENDED as 出席', () => {
    expect(attendanceSummary(['ATTENDED'])).toEqual({ attending: 1, notAttending: 0, unanswered: 0 });
  });

  it('counts LATE (遅刻) as 出席 - actually attended, must not be counted as 欠席', () => {
    expect(attendanceSummary(['LATE'])).toEqual({ attending: 1, notAttending: 0, unanswered: 0 });
  });

  it('counts ABSENT as 欠席', () => {
    expect(attendanceSummary(['ABSENT'])).toEqual({ attending: 0, notAttending: 1, unanswered: 0 });
  });

  it('counts UNANSWERED as 未回答', () => {
    expect(attendanceSummary(['UNANSWERED'])).toEqual({ attending: 0, notAttending: 0, unanswered: 1 });
  });

  it('counts EARLY_LEFT (早退) as 出席 - actually attended, must not be counted as 欠席', () => {
    expect(attendanceSummary(['EARLY_LEFT'])).toEqual({ attending: 1, notAttending: 0, unanswered: 0 });
  });

  it('tallies a mixed roster correctly, including EARLY_LEFT', () => {
    expect(
      attendanceSummary(['ATTENDED', 'LATE', 'EARLY_LEFT', 'ABSENT', 'UNANSWERED', 'ATTENDING', 'NOT_ATTENDING'])
    ).toEqual({
      attending: 4,
      notAttending: 2,
      unanswered: 1,
    });
  });
});
