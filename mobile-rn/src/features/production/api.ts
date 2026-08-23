import type { ApiClient } from '@/api/client';
import type { CurrentPerson, Production } from '@/types/api';

export function fetchCurrentPerson(client: ApiClient): Promise<CurrentPerson> {
  return client.get<CurrentPerson>('/me');
}

/** GET /productions is already Membership-scoped server-side (Phase
 * 5.0's Backend API Mapping) - no client-side filtering needed. Returns
 * the caller's Productions across every Organization they belong to;
 * Organization-scoping happens client-side (see useOrganizationProductions) since
 * no organization_id filter parameter exists on this endpoint. */
export function fetchProductions(client: ApiClient): Promise<Production[]> {
  return client.get<Production[]>('/productions');
}

/** GET /productions/{id} (ProductionRestController::get) - real,
 * existing endpoint, used to show the Production name in the Production
 * Shell header. */
export function fetchProduction(client: ApiClient, id: string): Promise<Production> {
  return client.get<Production>(`/productions/${id}`);
}
