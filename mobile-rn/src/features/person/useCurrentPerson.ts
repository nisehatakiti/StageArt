import { useQuery } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';
import { fetchCurrentPerson } from '@/features/production/api';

export function useCurrentPerson() {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['me'],
    queryFn: () => fetchCurrentPerson(apiClient),
    enabled: status === 'authenticated',
  });
}
