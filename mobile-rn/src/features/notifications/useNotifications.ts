import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchProductionNotifications, markNotificationRead } from './api';

/** Query Key: `['production-notifications', productionId]`, matching the
 * existing `['production-accounting', productionId]` / `['production-
 * timetable', productionId, ...]` convention. */
export function useProductionNotifications(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['production-notifications', productionId],
    queryFn: () => fetchProductionNotifications(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });
}

/**
 * Phase 7.1: invalidates the same existing Query Key on success rather
 * than introducing a new one (§9/§11 of this Phase's instruction) - the
 * refetch is what makes `is_read` update on screen; read state is never
 * set optimistically or persisted locally, since the Backend is the
 * sole Source of Truth for it.
 */
export function useMarkNotificationRead(productionId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (notificationId: string) => markNotificationRead(apiClient, notificationId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['production-notifications', productionId] });
    },
  });
}
