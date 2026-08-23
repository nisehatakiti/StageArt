const STATUS_LABELS: Record<string, string> = {
  UNANSWERED: '未回答',
  AVAILABLE: '参加可能',
  UNAVAILABLE: '参加不可',
  ATTENDING: '出席',
  NOT_ATTENDING: '欠席',
  ATTENDED: '出席（実績）',
  LATE: '遅刻（実績）',
  ABSENT: '欠席（実績）',
};

export function statusLabel(status: string): string {
  return STATUS_LABELS[status] ?? status;
}

/**
 * The legal response choices per phase, taken directly from
 * RehearsalAttendance.php's respondScheduleAdjustment/
 * respondAttendanceConfirmation guards (AVAILABLE/UNAVAILABLE and
 * ATTENDING/NOT_ATTENDING respectively) - an enum-legality fact, not a
 * "who can do what" Authorization rule, so presenting only these
 * choices is not the kind of business-rule duplication §18 warns
 * against (the Backend still independently rejects anything illegal).
 */
export function responseOptionsForPhase(phase: string): string[] {
  return phase === 'SCHEDULE_ADJUSTMENT' ? ['AVAILABLE', 'UNAVAILABLE'] : ['ATTENDING', 'NOT_ATTENDING'];
}

export const ACTUAL_STATUS_OPTIONS = ['ATTENDED', 'LATE', 'ABSENT'];
