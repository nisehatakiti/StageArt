import { useQuery } from '@tanstack/react-query';

import type { ApiClient } from '@/api/client';
import { useAuth } from '@/auth/AuthContext';

/**
 * GET /organizations/{id}/production-results - 団体会計の公演別実績
 * (OrganizationAccountingAndProductionCostPolicy.md §2). 売上 / 原価 / 収支
 * are computed by the server from the Productions' POSTED Journal Entries.
 */
export type OrganizationProductionResult = {
  production_id: string;
  name: string;
  revenue: number;
  cost: number;
  profit: number;
  closing_confirmed: boolean;
};

export type OrganizationProductionResults = {
  organization_id: string;
  productions: OrganizationProductionResult[];
  totals: { revenue: number; cost: number; profit: number };
  currency: string;
};

export function fetchOrganizationProductionResults(client: ApiClient, organizationId: string): Promise<OrganizationProductionResults> {
  return client.get<OrganizationProductionResults>(`/organizations/${organizationId}/production-results`);
}

export function useOrganizationProductionResults(organizationId: string | undefined) {
  const { apiClient, status } = useAuth();

  return useQuery({
    queryKey: ['organization-production-results', organizationId],
    queryFn: () => fetchOrganizationProductionResults(apiClient, organizationId as string),
    enabled: status === 'authenticated' && !!organizationId,
  });
}
