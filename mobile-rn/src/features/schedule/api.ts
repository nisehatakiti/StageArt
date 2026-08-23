import type { ApiClient } from '@/api/client';
import type { Participant, ScheduleComment, TimetableItem } from '@/types/api';

/**
 * GET /productions/{id}/timetable-items - the Production Schedule Read
 * Model (Timetable.md's "Production Schedule (Read Model)"). Aggregates
 * TimetableItems across every Rehearsal of the Production, already
 * limited server-side to each Rehearsal's latest PUBLISHED Version
 * (ListProductionTimetableItemsUseCase) - DRAFT/ARCHIVED never appear
 * here. No Role/ParticipantType filter parameter exists, and none is
 * added here (Shared Visibility Principle).
 *
 * `from`/`to` are optional wall-clock strings ("YYYY-MM-DD HH:mm:ss").
 * The Backend re-derives them to a naive wall-clock DateTimeImmutable
 * for comparison regardless of any offset supplied (see
 * ListProductionTimetableItemsUseCase::parseOptionalDateTime), so an
 * offset-free local wall-clock string is passed here rather than an
 * ISO 8601 string with a timezone suffix, matching the Backend's own
 * documented convention instead of introducing an independent one.
 */
export function fetchProductionTimetableItems(
  client: ApiClient,
  productionId: string,
  range?: { from?: string; to?: string }
): Promise<TimetableItem[]> {
  return client.get<TimetableItem[]>(`/productions/${productionId}/timetable-items`, {
    from: range?.from,
    to: range?.to,
  });
}

/** GET /timetable-items/{id} - used by the Schedule Item Detail screen
 * (§20); the list screen already holds the full item shape, but
 * fetching by id keeps the Detail route independently deep-linkable and
 * matches TimetableItemRestController's intended single-item read. */
export function fetchTimetableItem(client: ApiClient, id: string): Promise<TimetableItem> {
  return client.get<TimetableItem>(`/timetable-items/${id}`);
}

/** GET /productions/{id}/participants - used only to resolve the
 * current Person's own Participant Type(s), for Personal Highlight
 * (Timetable.md's "Personal Highlight Principle"). Never used to filter
 * the Schedule Item set itself. */
export function fetchParticipants(client: ApiClient, productionId: string): Promise<Participant[]> {
  return client.get<Participant[]>(`/productions/${productionId}/participants`);
}

/** GET /timetable-items/{id}/schedule-comments - Production Member-wide
 * visibility, no Role filtering (ScheduleComment.md's "Visibility"). */
export function fetchTimetableItemComments(client: ApiClient, timetableItemId: string): Promise<ScheduleComment[]> {
  return client.get<ScheduleComment[]>(`/timetable-items/${timetableItemId}/schedule-comments`);
}

/** POST /timetable-items/{id}/schedule-comments */
export function postTimetableItemComment(
  client: ApiClient,
  timetableItemId: string,
  body: string
): Promise<ScheduleComment> {
  return client.post<ScheduleComment>(`/timetable-items/${timetableItemId}/schedule-comments`, { body });
}

/**
 * GET /rehearsals/{id}/schedule-comments (Phase 6.3) - the Rehearsal-
 * level comment thread, distinct from a single TimetableItem's thread
 * above. Was already routed and implemented server-side but unused by
 * any Mobile client until this Phase's API Coverage Matrix surfaced it.
 * Same Production-Member-wide visibility as the TimetableItem variant.
 */
export function fetchRehearsalComments(client: ApiClient, rehearsalId: string): Promise<ScheduleComment[]> {
  return client.get<ScheduleComment[]>(`/rehearsals/${rehearsalId}/schedule-comments`);
}

/** POST /rehearsals/{id}/schedule-comments */
export function postRehearsalComment(client: ApiClient, rehearsalId: string, body: string): Promise<ScheduleComment> {
  return client.post<ScheduleComment>(`/rehearsals/${rehearsalId}/schedule-comments`, { body });
}

/**
 * PUT /schedule-comments/{id} (Phase 6.4) - shared by both comment
 * variants (Rehearsal-level and TimetableItem-level both create rows
 * addressable by this one endpoint; see ScheduleCommentRestController's
 * single `update`/`delete` route, not split per variant). Backend
 * enforces author-only editing (UpdateScheduleCommentUseCase) - this
 * Client does not pre-check authorship, it just calls the endpoint and
 * lets a non-author's attempt come back as a 403.
 */
export function updateScheduleComment(client: ApiClient, commentId: string, body: string): Promise<ScheduleComment> {
  return client.put<ScheduleComment>(`/schedule-comments/${commentId}`, { body });
}

/**
 * DELETE /schedule-comments/{id} - Backend allows the author OR the
 * Production's PrimaryManager/a REHEARSAL_MANAGER Delegate
 * (DeleteScheduleCommentUseCase::canManageRehearsals) to delete any
 * given comment; this Client does not replicate that check, it lets an
 * unauthorized attempt come back as a 403.
 */
export function deleteScheduleComment(client: ApiClient, commentId: string): Promise<void> {
  return client.delete<void>(`/schedule-comments/${commentId}`);
}
