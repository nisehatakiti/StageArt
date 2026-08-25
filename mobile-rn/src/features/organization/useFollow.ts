import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';

import { fetchMyFollows, followOrganization, unfollowOrganization } from './api';

/** The caller's own actively-followed Organizations - used to derive a
 * Follow button's initial "am I already following this?" state (see
 * fetchMyFollows()'s own docblock). */
export function useMyFollows() {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['my-follows'],
    queryFn: () => fetchMyFollows(apiClient),
    enabled: status === 'authenticated',
  });
}

/**
 * Both mutations invalidate `['my-follows']` (button state elsewhere),
 * `['my-dashboard']` (the Home "フォロー中の新着" feed depends on which
 * Organizations are followed) and `['public-organization', slug]` (the
 * public page's own `follower_count` - see fetchPublicOrganizationBySlug)
 * so every already-mounted consumer reflects the change without a manual
 * refetch.
 */
export function useFollowOrganization(organizationSlug?: string) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  function invalidate() {
    queryClient.invalidateQueries({ queryKey: ['my-follows'] });
    queryClient.invalidateQueries({ queryKey: ['my-dashboard'] });
    if (organizationSlug) {
      queryClient.invalidateQueries({ queryKey: ['public-organization', organizationSlug] });
    }
  }

  const follow = useMutation({
    mutationFn: (organizationId: string) => followOrganization(apiClient, organizationId),
    onSuccess: invalidate,
  });

  const unfollow = useMutation({
    mutationFn: (organizationId: string) => unfollowOrganization(apiClient, organizationId),
    onSuccess: invalidate,
  });

  return { follow, unfollow };
}
