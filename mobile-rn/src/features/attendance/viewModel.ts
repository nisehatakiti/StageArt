const STATUS_LABELS: Record<string, string> = {
  UNANSWERED: '未回答',
  AVAILABLE: '参加可能',
  UNAVAILABLE: '参加不可',
  ATTENDING: '出席',
  NOT_ATTENDING: '欠席',
  ATTENDED: '出席（実績）',
  LATE: '遅刻（実績）',
  EARLY_LEFT: '早退（実績）',
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

export const ACTUAL_STATUS_OPTIONS = ['ATTENDED', 'LATE', 'EARLY_LEFT', 'ABSENT'];

/**
 * 出席状況サマリー (出席/欠席/未回答) - groups the 9 real
 * RehearsalAttendanceStatus values (both Phases, see
 * RehearsalAttendanceStatus.php's VALID list - no other value exists)
 * into the 3 buckets the summary displays.
 *
 * LATE (遅刻・実績) and EARLY_LEFT (早退・実績) both count as 出席:
 * confirmed business rule - someone who arrived late or left early still
 * actually attended, so neither must be counted as 欠席.
 *
 * AVAILABLE (参加可能, SCHEDULE_ADJUSTMENT-phase) is left in the 出席
 * bucket unchanged - its classification was not part of this round's
 * confirmed scope and is deliberately not revisited here, even though
 * it represents availability rather than an attendance record like
 * ATTENDED/LATE/EARLY_LEFT do.
 *
 * UNAVAILABLE/NOT_ATTENDING/ABSENT count as 欠席; UNANSWERED counts as
 * 未回答. No "未定" bucket - every real status value falls into exactly
 * one of these 3 groups.
 */
const ATTENDING_STATUSES = new Set(['AVAILABLE', 'ATTENDING', 'ATTENDED', 'LATE', 'EARLY_LEFT']);
const NOT_ATTENDING_STATUSES = new Set(['UNAVAILABLE', 'NOT_ATTENDING', 'ABSENT']);

export function attendanceSummary(statuses: string[]): { attending: number; notAttending: number; unanswered: number } {
  let attending = 0;
  let notAttending = 0;
  let unanswered = 0;

  for (const status of statuses) {
    if (ATTENDING_STATUSES.has(status)) {
      attending += 1;
    } else if (NOT_ATTENDING_STATUSES.has(status)) {
      notAttending += 1;
    } else {
      unanswered += 1;
    }
  }

  return { attending, notAttending, unanswered };
}
