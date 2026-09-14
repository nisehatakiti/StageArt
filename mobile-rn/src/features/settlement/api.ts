import type { ApiClient } from '@/api/client';
import type { ProductionSettlementSummary } from '@/types/api';

/** GET /productions/{id}/settlement - ProductionSettlementScreen.md
 * (Chapter 29) 精算 screen Read Model. */
export function fetchProductionSettlementSummary(client: ApiClient, productionId: string): Promise<ProductionSettlementSummary> {
  return client.get<ProductionSettlementSummary>(`/productions/${productionId}/settlement`);
}

/** POST /productions/{id}/settlement/members/{personId}/settle - settles
 * exactly one member's currently-outstanding Ticket Back to 0円. */
export function settleProductionMember(client: ApiClient, productionId: string, personId: string): Promise<void> {
  return client.post(`/productions/${productionId}/settlement/members/${personId}/settle`);
}

/** POST /productions/{id}/settlement/members/{personId}/cancel-settlement -
 * reverses that member's MOST RECENT settle action only (Phase 5 §7's
 * "精算済み" checkbox unchecked), not their whole settlement history. */
export function cancelProductionMemberSettlement(client: ApiClient, productionId: string, personId: string): Promise<void> {
  return client.post(`/productions/${productionId}/settlement/members/${personId}/cancel-settlement`);
}
