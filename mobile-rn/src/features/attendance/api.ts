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
 * DRAFT; RehearsalAttendance SCHEDULE_ADJUSTMENT-phase records are
 * created automatically for eligible Participants server-side, see
 * CreateRehearsalUseCase.php). */
export function createRehearsal(
  client: ApiClient,
  productionId: string,
  fields: { title: string; startDateTime?: string; endDateTime?: string; timezone?: string; location?: string }
): Promise<Rehearsal> {
  return client.post<Rehearsal>(`/productions/${productionId}/rehearsals`, {
    title: fields.title,
    start_date_time: fields.startDateTime,
    end_date_time: fields.endDateTime,
    timezone: fields.timezone,
    location: fields.location,
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
