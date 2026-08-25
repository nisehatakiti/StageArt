import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { disableJoinKey, fetchOrganizationJoinKeys, fetchProductionJoinKeys, issueOrganizationJoinKey, issueProductionJoinKey } from './api';

export function useOrganizationJoinKeys(organizationId: string | undefined) {
  const { apiClient, status } = useAuth();

  const query = useQuery({
    queryKey: ['organization-join-keys', organizationId],
    queryFn: () => fetchOrganizationJoinKeys(apiClient, organizationId as string),
    enabled: status === 'authenticated' && !!organizationId,
  });

  const queryClient = useQueryClient();
  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['organization-join-keys', organizationId] });
  }

  const issue = useMutation({
    mutationFn: () => issueOrganizationJoinKey(apiClient, organizationId as string),
    onSuccess: invalidate,
  });

  const disable = useMutation({
    mutationFn: (joinKeyId: string) => disableJoinKey(apiClient, joinKeyId),
    onSuccess: invalidate,
  });

  return { query, issue, disable };
}

export function useProductionJoinKeys(productionId: string | undefined) {
  const { apiClient, status } = useAuth();

  const query = useQuery({
    queryKey: ['production-join-keys', productionId],
    queryFn: () => fetchProductionJoinKeys(apiClient, productionId as string),
    enabled: status === 'authenticated' && !!productionId,
  });

  const queryClient = useQueryClient();
  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['production-join-keys', productionId] });
  }

  const issue = useMutation({
    mutationFn: () => issueProductionJoinKey(apiClient, productionId as string),
    onSuccess: invalidate,
  });

  const disable = useMutation({
    mutationFn: (joinKeyId: string) => disableJoinKey(apiClient, joinKeyId),
    onSuccess: invalidate,
  });

  return { query, issue, disable };
}
