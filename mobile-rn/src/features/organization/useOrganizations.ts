import { useQuery } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchOrganizations } from './api';

export function useOrganizations() {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['organizations'],
    queryFn: () => fetchOrganizations(apiClient),
    enabled: status === 'authenticated',
  });
}
