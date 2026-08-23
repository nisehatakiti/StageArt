/**
 * Rehearsal.md's Status Lifecycle is DRAFT -> SCHEDULED -> CONFIRMED ->
 * ACTIVE -> COMPLETED (CANCELLED reachable from the first four).
 * RehearsalAttendanceUseCaseTest.php's own setup only creates
 * ATTENDANCE_CONFIRMATION-phase records via ConfirmRehearsalUseCase
 * (i.e. at the DRAFT/SCHEDULED -> CONFIRMED transition) - no Blueprint
 * doc states outright "phase X is used while Rehearsal is in status Y."
 * This mapping is therefore a disclosed judgment call, not a literal
 * Blueprint/API contract: before CONFIRMED, only SCHEDULE_ADJUSTMENT
 * records are assumed to exist; CONFIRMED and afterward,
 * ATTENDANCE_CONFIRMATION. See this Phase's report §08 Open Items.
 */
export function phaseForRehearsalStatus(status: string): 'SCHEDULE_ADJUSTMENT' | 'ATTENDANCE_CONFIRMATION' {
  return status === 'DRAFT' || status === 'SCHEDULED' ? 'SCHEDULE_ADJUSTMENT' : 'ATTENDANCE_CONFIRMATION';
}
