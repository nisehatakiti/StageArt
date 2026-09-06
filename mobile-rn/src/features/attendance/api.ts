import type { ApiClient } from '@/api/client';
import type { Rehearsal, RehearsalAttendance } from '@/types/api';

/**
 * GET /productions/{id}/rehearsals (Backend's ListRehearsalsUseCase).
 * Lives here rather than a separate features/rehearsal/ module since
 * Attendance is, at this Phase, the only mobile-rn consumer of the
 * Rehearsal list - see this Phase's report §08 for why a new module
 * was not created for a single caller.
 */
export function fetchRehearsals(client: ApiClient, productionId: string): Promise<Rehearsal[]> {
  return client.get<Rehearsal[]>(`/productions/${productionId}/rehearsals`);
}

/** GET /rehearsals/{id} - a single Rehearsal, used to derive which
 * Attendance phase to query (see features/attendance/phase.ts). */
export function fetchRehearsal(client: ApiClient, rehearsalId: string): Promise<Rehearsal> {
  return client.get<Rehearsal>(`/rehearsals/${rehearsalId}`);
}

/** POST /productions/{id}/rehearsals - creates a Rehearsal (starts at
 * SCHEDULED). `targetPersonIds` becomes the exact set of Production
 * members who get a SCHEDULE_ADJUSTMENT RehearsalAttendance record - an
 * unselected Production member is not an Attendance target for this
 * Rehearsal (CreateRehearsalUseCase.php no longer auto-targets every
 * active member). Omitting it (or passing []) creates zero Attendance
 * records. */
export function createRehearsal(
  client: ApiClient,
  productionId: string,
  fields: {
    title: string;
    startDateTime?: string;
    endDateTime?: string;
    timezone?: string;
    location?: string;
    targetPersonIds?: string[];
  }
): Promise<Rehearsal> {
  return client.post<Rehearsal>(`/productions/${productionId}/rehearsals`, {
    title: fields.title,
    start_date_time: fields.startDateTime,
    end_date_time: fields.endDateTime,
    timezone: fields.timezone,
    location: fields.location,
    person_ids: fields.targetPersonIds,
  });
}

/** POST /rehearsals/{id}/confirm - "稽古情報の確定" (docs/04-HomeRoleBasedMenu.md
 * §07's 稽古管理). Moves the Attendance phase from SCHEDULE_ADJUSTMENT to
 * ATTENDANCE_CONFIRMATION (see features/attendance/phase.ts's own
 * disclosed mapping) - ATTENDANCE_CONFIRMATION-phase records are created
 * automatically server-side by this action (ConfirmRehearsalUseCase.php). */
export function confirmRehearsal(client: ApiClient, rehearsalId: string): Promise<Rehearsal> {
  return client.post<Rehearsal>(`/rehearsals/${rehearsalId}/confirm`);
}

/** POST /rehearsals/{id}/cancel - "中止する" (稽古を中止する). Soft
 * status-only transition to CANCELLED (CancelRehearsalUseCase.php); the
 * record is never physically deleted and keeps appearing in the
 * Rehearsal list with its CANCELLED status. Valid from DRAFT/SCHEDULED/
 * CONFIRMED/ACTIVE (Rehearsal.md's own Status Lifecycle "中止の場合"
 * section - anything short of the two terminal statuses). */
export function cancelRehearsal(client: ApiClient, rehearsalId: string): Promise<Rehearsal> {
  return client.post<Rehearsal>(`/rehearsals/${rehearsalId}/cancel`);
}

/** POST /rehearsals/{id}/activate - "稽古を開始する" (Rehearsal.md's own
 * ACTIVE definition: "Rehearsalが実施中である状態"). Only a CONFIRMED
 * Rehearsal may become ACTIVE (ActivateRehearsalUseCase.php). */
export function activateRehearsal(client: ApiClient, rehearsalId: string): Promise<Rehearsal> {
  return client.post<Rehearsal>(`/rehearsals/${rehearsalId}/activate`);
}

/** POST /rehearsals/{id}/complete - "実施済みにする" (Rehearsal.md's own
 * COMPLETED definition: "Rehearsalが実施済みとなった状態"). Only an ACTIVE
 * Rehearsal may become COMPLETED (CompleteRehearsalUseCase.php). */
export function completeRehearsal(client: ApiClient, rehearsalId: string): Promise<Rehearsal> {
  return client.post<Rehearsal>(`/rehearsals/${rehearsalId}/complete`);
}

/** GET /rehearsals/{id}/attendances?phase=X - the full roster for one
 * Rehearsal/phase, not just the caller's own record (see
 * RehearsalAttendanceRestController.php: read is Production-membership-
 * wide, not self-only). */
export function fetchRehearsalAttendances(
  client: ApiClient,
  rehearsalId: string,
  phase: string
): Promise<RehearsalAttendance[]> {
  return client.get<RehearsalAttendance[]>(`/rehearsals/${rehearsalId}/attendances`, { phase });
}

/** POST /rehearsals/{id}/attendances - "稽古詳細画面で未選択メンバーを追加".
 * Adds RehearsalAttendance targets for Production members who were not
 * selected at creation/confirm time. Backend validates each personId is
 * a currently-ACTIVE, Person-subject Participant of this Rehearsal's
 * Production, and is idempotent per Person - someone who already has an
 * Attendance record for the Rehearsal's current phase is left untouched
 * (no resend), only genuinely new targets get created
 * (AddRehearsalAttendanceTargetsUseCase.php). Returns only the
 * newly-created records. */
export function addRehearsalAttendanceTargets(
  client: ApiClient,
  rehearsalId: string,
  personIds: string[]
): Promise<RehearsalAttendance[]> {
  return client.post<RehearsalAttendance[]>(`/rehearsals/${rehearsalId}/attendances`, { person_ids: personIds });
}

/** PUT /rehearsal-attendances/{id}/respond - self-response only; the
 * Backend rejects (403) if the caller does not own this record. */
export function respondRehearsalAttendance(
  client: ApiClient,
  attendanceId: string,
  status: string
): Promise<RehearsalAttendance> {
  return client.put<RehearsalAttendance>(`/rehearsal-attendances/${attendanceId}/respond`, { status });
}

/** PUT /rehearsal-attendances/{id}/record-actual-status - the
 * PrimaryManager/REHEARSAL_MANAGER-only day-of result correction. This
 * Client does not pre-filter who may call it (§24/§25: no client-side
 * Role duplication) - an unauthorized attempt surfaces the Backend's
 * own 403 via ApiError/getErrorMessage. */
export function recordActualRehearsalAttendanceStatus(
  client: ApiClient,
  attendanceId: string,
  status: string
): Promise<RehearsalAttendance> {
  return client.put<RehearsalAttendance>(`/rehearsal-attendances/${attendanceId}/record-actual-status`, { status });
}
