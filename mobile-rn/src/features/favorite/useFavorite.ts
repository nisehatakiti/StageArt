import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { addFavorite, fetchMyFavorites, removeFavorite } from './api';

export function useMyFavorites() {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['my-favorites'],
    queryFn: () => fetchMyFavorites(apiClient),
    enabled: status === 'authenticated',
  });
}

export function useToggleFavorite() {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['my-favorites'] });
  }

  const add = useMutation({
    mutationFn: ({ targetType, targetId }: { targetType: 'ORGANIZATION' | 'PRODUCTION'; targetId: string }) =>
      addFavorite(apiClient, targetType, targetId),
    onSuccess: invalidate,
  });

  const remove = useMutation({
    mutationFn: ({ targetType, targetId }: { targetType: 'ORGANIZATION' | 'PRODUCTION'; targetId: string }) =>
      removeFavorite(apiClient, targetType, targetId),
    onSuccess: invalidate,
  });

  return { add, remove };
}
