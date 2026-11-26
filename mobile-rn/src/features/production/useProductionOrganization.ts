import { useQuery } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';
import { fetchProjects } from '@/features/organization/api';
import { useOrganizations } from '@/features/organization/useOrganizations';
import type { Organization, Production } from '@/types/api';

/**
 * StageArt Web版 公演管理 Phase: shared by every `/productions/[id]/*`
 * screen (management top, edit, participants, publish) - each needs the
 * same client-side Production -> Project -> Organization resolution
 * (Production has no direct organization_id - see
 * src/types/api.ts's Project docblock) to build its own breadcrumb
 * (Dashboard > 団体 > {組織名} > 公演 > {公演名}) and to read the
 * Organization's own slug for the public page link. Extracted here
 * rather than duplicated once per screen.
 */
export function useProductionOrganization(production: Production | undefined | null): {
  organization: Organization | null;
  isLoading: boolean;
} {
  const { apiClient } = useAuth();
  const organizationsQuery = useOrganizations();
  const projectsQuery = useQuery({
    queryKey: ['projects'],
    queryFn: () => fetchProjects(apiClient),
  });

  const project = projectsQuery.data?.find((candidate) => candidate.id === production?.project_id) ?? null;
  const organization = organizationsQuery.data?.find((candidate) => candidate.id === project?.organization_id) ?? null;

  return {
    organization,
    isLoading: organizationsQuery.isLoading || projectsQuery.isLoading,
  };
}
