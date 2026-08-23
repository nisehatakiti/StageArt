import { ACTUAL_STATUS_OPTIONS, responseOptionsForPhase, statusLabel } from './viewModel';

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
  it('is exactly ATTENDED/LATE/ABSENT', () => {
    expect(ACTUAL_STATUS_OPTIONS).toEqual(['ATTENDED', 'LATE', 'ABSENT']);
  });
});
