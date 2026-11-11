import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import {
  createTimetableItem,
  fetchDraftTimetableVersionId,
  fetchPublishedRehearsalTimetableItems,
  fetchRehearsalTimetableItems,
  findDraftTimetableVersionId,
  publishTimetableVersion,
  saveAndPublishTimetable,
  type TimetableItemInput,
} from './api';

/** PR #7 R8 (2-5): what the independent timetable screen edits - the
 * DRAFT's items when a DRAFT exists, otherwise the PUBLISHED items. */
export function useTimetableEditorItems(rehearsalId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['timetable-editor-items', rehearsalId],
    queryFn: async () => {
      const draftId = await findDraftTimetableVersionId(apiClient, rehearsalId as string);
      const items =
        draftId !== null
          ? await fetchRehearsalTimetableItems(apiClient, rehearsalId as string)
          : await fetchPublishedRehearsalTimetableItems(apiClient, rehearsalId as string);
      return { hasDraft: draftId !== null, items };
    },
    enabled: status === 'authenticated' && !!rehearsalId,
  });
}

export function useSaveAndPublishTimetable(rehearsalId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (items: TimetableItemInput[]) => saveAndPublishTimetable(apiClient, rehearsalId as string, items),
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['timetable-editor-items', rehearsalId] });
      queryClient.invalidateQueries({ queryKey: ['rehearsal-draft-timetable-items', rehearsalId] });
      queryClient.invalidateQueries({ queryKey: ['production-timetable'] });
    },
  });
}

export function useRehearsalDraftTimetableItems(rehearsalId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['rehearsal-draft-timetable-items', rehearsalId],
    queryFn: () => fetchRehearsalTimetableItems(apiClient, rehearsalId as string),
    enabled: status === 'authenticated' && !!rehearsalId,
  });
}

export function useCreateTimetableItem(rehearsalId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: { title: string; startDateTime: string; category?: string; venue?: string }) =>
      createTimetableItem(apiClient, rehearsalId as string, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['rehearsal-draft-timetable-items', rehearsalId] }),
  });
}

/** Resolves the current DRAFT Timetable Version id, then publishes it in
 * one step - the Web UI never needs to show the intermediate id. */
export function usePublishRehearsalTimetable(rehearsalId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (changeSummary?: string) => {
      const draftId = await fetchDraftTimetableVersionId(apiClient, rehearsalId as string);
      return publishTimetableVersion(apiClient, draftId, changeSummary);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['rehearsal-draft-timetable-items', rehearsalId] });
      queryClient.invalidateQueries({ queryKey: ['production-timetable-items'] });
    },
  });
}
