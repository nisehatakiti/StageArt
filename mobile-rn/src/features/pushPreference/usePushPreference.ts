import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchPushPreference, updatePushPreference } from './api';

export function usePushPreference() {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['push-preference'],
    queryFn: () => fetchPushPreference(apiClient),
    enabled: status === 'authenticated',
  });
}

export function useUpdatePushPreference() {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (enabled: boolean) => updatePushPreference(apiClient, enabled),
    onSuccess: (updated) => {
      queryClient.setQueryData(['push-preference'], updated);
    },
  });
}
