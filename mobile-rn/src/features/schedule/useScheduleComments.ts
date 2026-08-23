import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import {
  deleteScheduleComment,
  fetchRehearsalComments,
  fetchTimetableItemComments,
  postRehearsalComment,
  postTimetableItemComment,
  updateScheduleComment,
} from './api';

/** §22: no Role-based filtering - every Production Member sees the same
 * comment list (ScheduleComment.md's "Visibility"). */
export function useTimetableItemComments(timetableItemId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['schedule-comments', timetableItemId],
    queryFn: () => fetchTimetableItemComments(apiClient, timetableItemId as string),
    enabled: status === 'authenticated' && !!timetableItemId,
  });
}

export function usePostScheduleComment(timetableItemId: string) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (body: string) => postTimetableItemComment(apiClient, timetableItemId, body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['schedule-comments', timetableItemId] });
    },
  });
}

/** Phase 6.3: Rehearsal-level counterpart, same Query Key shape scoped
 * by rehearsalId instead of timetableItemId so the two never collide. */
export function useRehearsalComments(rehearsalId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['rehearsal-comments', rehearsalId],
    queryFn: () => fetchRehearsalComments(apiClient, rehearsalId as string),
    enabled: status === 'authenticated' && !!rehearsalId,
  });
}

export function usePostRehearsalComment(rehearsalId: string) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (body: string) => postRehearsalComment(apiClient, rehearsalId, body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['rehearsal-comments', rehearsalId] });
    },
  });
}

/**
 * Phase 6.4: PUT /schedule-comments/{id} against the same
 * `['rehearsal-comments', rehearsalId]` Query Key (§10 - no new Query
 * Key introduced). `updateScheduleComment`/`deleteScheduleComment` are
 * the shared, endpoint-level functions (§11 - not duplicated per
 * comment variant); only these two Rehearsal-scoped hooks are wired
 * into a screen this Phase, per this Phase's explicit UI scope.
 */
export function useUpdateRehearsalComment(rehearsalId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (params: { commentId: string; body: string }) => updateScheduleComment(apiClient, params.commentId, params.body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['rehearsal-comments', rehearsalId] });
    },
  });
}

export function useDeleteRehearsalComment(rehearsalId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (commentId: string) => deleteScheduleComment(apiClient, commentId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['rehearsal-comments', rehearsalId] });
    },
  });
}

/**
 * Phase 6.5: TimetableItem-level counterpart to
 * useUpdateRehearsalComment/useDeleteRehearsalComment above - same
 * shared `updateScheduleComment`/`deleteScheduleComment` functions
 * (§05/§11 of Phase 6.4 - one Backend route regardless of comment
 * variant), invalidating the existing `['schedule-comments',
 * timetableItemId]` Query Key instead. Closes the Open Item Phase 6.4
 * disclosed (Edit/Delete only wired into the Rehearsal screen so far).
 */
export function useUpdateTimetableItemComment(timetableItemId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (params: { commentId: string; body: string }) => updateScheduleComment(apiClient, params.commentId, params.body),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['schedule-comments', timetableItemId] });
    },
  });
}

export function useDeleteTimetableItemComment(timetableItemId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (commentId: string) => deleteScheduleComment(apiClient, commentId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['schedule-comments', timetableItemId] });
    },
  });
}
