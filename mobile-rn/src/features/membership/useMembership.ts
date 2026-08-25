import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import {
  approveMembershipRequest,
  fetchMyMemberships,
  fetchPendingMembershipRequests,
  rejectMembershipRequest,
  requestOrganizationMembership,
} from './api';

export function useMyMemberships() {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['my-memberships'],
    queryFn: () => fetchMyMemberships(apiClient),
    enabled: status === 'authenticated',
  });
}

export function usePendingMembershipRequests(organizationId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['pending-membership-requests', organizationId],
    queryFn: () => fetchPendingMembershipRequests(apiClient, organizationId as string),
    enabled: status === 'authenticated' && !!organizationId,
  });
}

export function useRequestOrganizationMembership() {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (fields: { organizationId?: string; joinKeyCode?: string }) =>
      requestOrganizationMembership(apiClient, fields),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['my-memberships'] }),
  });
}

export function useMembershipRequestDecision(organizationId: string | undefined) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['pending-membership-requests', organizationId] });
  }

  const approve = useMutation({
    mutationFn: (membershipId: string) => approveMembershipRequest(apiClient, membershipId),
    onSuccess: invalidate,
  });

  const reject = useMutation({
    mutationFn: (membershipId: string) => rejectMembershipRequest(apiClient, membershipId),
    onSuccess: invalidate,
  });

  return { approve, reject };
}
