import type { ApiClient } from '@/api/client';
import { ApiError } from '@/api/errors';
import type { Participant, ScheduleComment, TimetableItem } from '@/types/api';

/** GET /rehearsals/{id}/timetable-items - every item on this Rehearsal's
 * latest DRAFT Timetable Version (the authoring view - unlike
 * fetchProductionTimetableItems, which only ever shows PUBLISHED
 * content to participants). */
export function fetchRehearsalTimetableItems(client: ApiClient, rehearsalId: string): Promise<TimetableItem[]> {
  return client.get<TimetableItem[]>(`/rehearsals/${rehearsalId}/timetable-items/draft`);
}

/** POST /rehearsals/{id}/timetable-items - adds an item to this
 * Rehearsal's DRAFT Timetable Version (TimetableItemRestController.php). */
export function createTimetableItem(
  client: ApiClient,
  rehearsalId: string,
  fields: { title: string; startDateTime: string; category?: string; venue?: string }
): Promise<TimetableItem> {
  return client.post<TimetableItem>(`/rehearsals/${rehearsalId}/timetable-items`, {
    title: fields.title,
    start_date_time: fields.startDateTime,
    category: fields.category,
    venue: fields.venue,
  });
}

/** GET /rehearsals/{id}/timetable/draft - resolves the current DRAFT
 * Timetable Version's own id, needed to then call
 * POST /timetable-versions/{id}/publish. */
export function fetchDraftTimetableVersionId(client: ApiClient, rehearsalId: string): Promise<string> {
  return client.get<{ id: string }>(`/rehearsals/${rehearsalId}/timetable/draft`).then((result) => result.id);
}

/** GET /rehearsals/{id}/timetable-items - the items of this 予定's latest
 * PUBLISHED Timetable Version ([] when none is published). */
export function fetchPublishedRehearsalTimetableItems(client: ApiClient, rehearsalId: string): Promise<TimetableItem[]> {
  return client.get<TimetableItem[]>(`/rehearsals/${rehearsalId}/timetable-items`);
}

/** POST /rehearsals/{id}/timetable-versions - starts a DRAFT Version,
 * copying the current PUBLISHED items when there are any
 * (CreateNewTimetableVersionUseCase). 409 when a DRAFT already exists. */
export function createTimetableVersion(client: ApiClient, rehearsalId: string): Promise<{ id: string }> {
  return client.post<{ id: string }>(`/rehearsals/${rehearsalId}/timetable-versions`);
}

/** DELETE /timetable-items/{id} - only items of a DRAFT Version. */
export function deleteTimetableItem(client: ApiClient, timetableItemId: string): Promise<void> {
  return client.delete<void>(`/timetable-items/${timetableItemId}`);
}

/** The current DRAFT Version id, or null when this 予定 has no DRAFT
 * (GET /rehearsals/{id}/timetable/draft answers 404). */
export async function findDraftTimetableVersionId(client: ApiClient, rehearsalId: string): Promise<string | null> {
  try {
    return await fetchDraftTimetableVersionId(client, rehearsalId);
  } catch (error) {
    if (error instanceof ApiError && error.statusCode === 404) {
      return null;
    }
    throw error;
  }
}

export type TimetableItemInput = {
  title: string;
  startDateTime: string;
  endDateTime: string | null;
  venue: string | null;
  notes: string | null;
  displayOrder: number;
};

/**
 * PR #7 R8 (2-5): 「保存」 on the independent timetable screen. Saves the
 * edited items into this 予定's DRAFT Version and then publishes it
 * (DRAFT -> PUBLISHED) - there is no separate 「内容を公開する」 step.
 *
 * 1. Use the existing DRAFT, or start one (copying the PUBLISHED items).
 * 2. Replace the DRAFT's items with the edited list (delete, then create
 *    in display order). Only DRAFT items are touched; the PUBLISHED
 *    Version and comments on its items are left as they are.
 * 3. Publish the DRAFT.
 * A failure part-way leaves an unpublished DRAFT; saving again resumes
 * from step 1 with that DRAFT, so nothing is published half-done.
 */
export async function saveAndPublishTimetable(
  client: ApiClient,
  rehearsalId: string,
  items: TimetableItemInput[]
): Promise<void> {
  let draftId = await findDraftTimetableVersionId(client, rehearsalId);

  if (draftId === null) {
    await createTimetableVersion(client, rehearsalId);
    draftId = await fetchDraftTimetableVersionId(client, rehearsalId);
  }

  for (const existing of await fetchRehearsalTimetableItems(client, rehearsalId)) {
    await deleteTimetableItem(client, existing.id);
  }

  for (const item of items) {
    await client.post<TimetableItem>(`/rehearsals/${rehearsalId}/timetable-items`, {
      title: item.title,
      start_date_time: item.startDateTime,
      end_date_time: item.endDateTime,
      venue: item.venue,
      notes: item.notes,
      display_order: item.displayOrder,
    });
  }

  await publishTimetableVersion(client, draftId);
}

/** POST /timetable-versions/{id}/publish - "稽古情報の確定" alongside
 * Rehearsal confirm() (see features/attendance/api.ts's confirmRehearsal) -
 * this specific action publishes the DRAFT Timetable Version's content
 * (items) so participants can see it via GET /productions/{id}/timetable-items. */
export function publishTimetableVersion(client: ApiClient, timetableVersionId: string, changeSummary?: string): Promise<unknown> {
  return client.post(`/timetable-versions/${timetableVersionId}/publish`, { change_summary: changeSummary });
}

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
