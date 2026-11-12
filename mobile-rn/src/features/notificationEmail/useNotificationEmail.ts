import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchNotificationEmailSettings, requestNotificationEmailChange } from './api';

export function useNotificationEmailSettings() {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['notification-email'],
    queryFn: () => fetchNotificationEmailSettings(apiClient),
    enabled: status === 'authenticated',
  });
}

export function useRequestNotificationEmailChange() {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (email: string) => requestNotificationEmailChange(apiClient, email),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notification-email'] });
    },
  });
}
