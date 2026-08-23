import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchRehearsalAttendances, recordActualRehearsalAttendanceStatus, respondRehearsalAttendance } from './api';

export function useRehearsalAttendances(rehearsalId: string | undefined, phase: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['rehearsal-attendances', rehearsalId, phase],
    queryFn: () => fetchRehearsalAttendances(apiClient, rehearsalId as string, phase as string),
    enabled: status === 'authenticated' && !!rehearsalId && !!phase,
  });
}

/** Invalidates the roster Query on success so the responder's own row
 * (and anyone else viewing the same roster after a refetch) reflects
 * the new status - matching schedule-comments' existing invalidate-on-
 * success mutation pattern rather than a manual cache write. */
export function useRespondRehearsalAttendance(rehearsalId: string | undefined, phase: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (params: { attendanceId: string; status: string }) =>
      respondRehearsalAttendance(apiClient, params.attendanceId, params.status),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['rehearsal-attendances', rehearsalId, phase] });
    },
  });
}

export function useRecordActualRehearsalAttendanceStatus(rehearsalId: string | undefined, phase: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (params: { attendanceId: string; status: string }) =>
      recordActualRehearsalAttendanceStatus(apiClient, params.attendanceId, params.status),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['rehearsal-attendances', rehearsalId, phase] });
    },
  });
}
