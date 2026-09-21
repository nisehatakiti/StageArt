import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useMemo } from 'react';

import { useAuth } from '@/auth/AuthContext';
import { fetchProjects } from '@/features/organization/api';

import { activateProduction, archiveProduction, cancelProduction, completeProduction, fetchProduction, fetchProductions } from './api';

/**
 * Organization-scoped Production list.
 *
 * GET /productions and GET /projects are both Membership-scoped
 * server-side but NOT Organization-scoped (no organization_id query
 * parameter exists on either endpoint - confirmed by reading
 * ProductionRestController.php / ProjectRestController.php). Per §21's
 * "無理に新APIを作らない", Organization scoping is therefore done
 * client-side, by resolving each Production's Organization through the
 * internal Project bridge (production.project_id -> project.organization_id)
 * and filtering the already-authorized, already-fetched full list.
 *
 * This is a display-only filter, not a security decision: every
 * Production and every Project in the underlying lists was already
 * independently authorized server-side (the caller's own Memberships/
 * PrimaryManager/ProductionDelegate relationships), so no Organization
 * the caller doesn't belong to can appear here regardless of this
 * filter (FrontendArchitecture.md §66's "Client Role/Local Permission
 * are not a Security Boundary" is respected: this filter changes what is
 * *displayed*, never what is *authorized*).
 *
 * Because both underlying lists are already the caller's complete,
 * cross-Organization data, switching Organization Context does not
 * require a network refetch to avoid showing stale cross-Organization
 * data - the derived filter recomputes synchronously. See
 * OrganizationContext.tsx's `selectOrganization` for the (defensive,
 * not strictly required) invalidation-on-switch that still runs, so a
 * just-changed Membership is picked up promptly too.
 *
 * Not fetched until an Organization is actually selected
 * (`organizationId !== null`): there is nothing to display for either
 * request before that point (the Organization picker only needs GET
 * /organizations), so firing them earlier is pure wasted network work.
 */
export function useOrganizationProductions(organizationId: string | null) {
  const { apiClient, status } = useAuth();
  const enabled = status === 'authenticated' && organizationId !== null;

  const productionsQuery = useQuery({
    queryKey: ['productions'],
    queryFn: () => fetchProductions(apiClient),
    enabled,
  });

  const projectsQuery = useQuery({
    queryKey: ['projects'],
    queryFn: () => fetchProjects(apiClient),
    enabled,
  });

  const data = useMemo(() => {
    if (!organizationId || !productionsQuery.data || !projectsQuery.data) {
      return undefined;
    }

    const projectIdsInOrganization = new Set(
      projectsQuery.data.filter((project) => project.organization_id === organizationId).map((project) => project.id)
    );

    return productionsQuery.data.filter((production) => projectIdsInOrganization.has(production.project_id));
  }, [organizationId, productionsQuery.data, projectsQuery.data]);

  return {
    data,
    isLoading: productionsQuery.isLoading || projectsQuery.isLoading,
    isError: productionsQuery.isError || projectsQuery.isError,
    error: productionsQuery.error ?? projectsQuery.error,
    isFetching: productionsQuery.isFetching || projectsQuery.isFetching,
    refetch: () => {
      productionsQuery.refetch();
      projectsQuery.refetch();
    },
  };
}

export function useProduction(id: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['production', id],
    queryFn: () => fetchProduction(apiClient, id as string),
    enabled: status === 'authenticated' && !!id,
  });
}

/** One shared shape for the existing Lifecycle Actions - each just
 * PATCHes and invalidates the same two caches, differing only in which
 * endpoint they call. */
function useProductionLifecycleAction(id: string | undefined, action: (client: ReturnType<typeof useAuth>['apiClient'], id: string) => Promise<unknown>) {
  const { apiClient } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => action(apiClient, id as string),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['production', id] });
      queryClient.invalidateQueries({ queryKey: ['productions'] });
    },
  });
}

export function useActivateProduction(id: string | undefined) {
  return useProductionLifecycleAction(id, activateProduction);
}

export function useCompleteProduction(id: string | undefined) {
  return useProductionLifecycleAction(id, completeProduction);
}

export function useArchiveProduction(id: string | undefined) {
  return useProductionLifecycleAction(id, archiveProduction);
}

export function useCancelProduction(id: string | undefined) {
  return useProductionLifecycleAction(id, cancelProduction);
}
