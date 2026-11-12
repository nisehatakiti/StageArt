import { useQueries, useQuery } from '@tanstack/react-query';

import { useAuth } from '@/auth/AuthContext';
import { fetchProductions } from '@/features/production/api';

import { fetchProductionAccounting } from './api';

/**
 * StageArt Blueprint再構成 Phase 1c §3: "経費精算" (the common-menu entry,
 * see useNavMenu.ts) is shown only when the Person is affiliated with a
 * Production that actually has accounting activity - never just because
 * a Membership/Participant relationship exists.
 *
 * `['productions']` is the same server-authorized (Membership/
 * Participant-scoped) list `useOrganizationProductions`/
 * `useMyManagedProductions` already read - reused here unfiltered, since
 * §3's condition is "所属・参加している" (any affiliation), not
 * management permission specifically. Each affiliated Production's own
 * accounting summary is then checked via the existing
 * `['production-accounting', id]` Query (the exact key
 * useProductionAccounting.ts already uses for the 会計 screen itself, so
 * this shares that cache rather than duplicating fetches once a screen
 * has been visited).
 *
 * No Organization-level accounting summary endpoint exists (confirmed
 * absent from the API - Budget/Actual data is Production-scoped only;
 * see AccountingPolicy.md's own note that Organization accounting as a
 * business *function* is a separate concern from the Production-scoped
 * data this Client can actually query today). This check is therefore
 * Production-affiliation-only, not a literal reading of "Organizationで
 * 会計管理が行われている" - flagged as a known gap in the Phase 1c report
 * rather than inventing an Organization-level signal that doesn't exist.
 */
export function useHasAccountingActivity(): boolean {
  const { apiClient, status } = useAuth();

  const productionsQuery = useQuery({
    queryKey: ['productions'],
    queryFn: () => fetchProductions(apiClient),
    enabled: status === 'authenticated',
  });

  const productionIds = productionsQuery.data?.map((production) => production.id) ?? [];

  const accountingQueries = useQueries({
    queries: productionIds.map((id) => ({
      queryKey: ['production-accounting', id],
      queryFn: () => fetchProductionAccounting(apiClient, id),
      enabled: status === 'authenticated',
      staleTime: 5 * 60 * 1000,
    })),
  });

  return accountingQueries.some((query) => query.data?.has_budget || query.data?.has_actual);
}
