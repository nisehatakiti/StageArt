import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { createTimetableItem, fetchDraftTimetableVersionId, fetchRehearsalTimetableItems, publishTimetableVersion } from './api';

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
