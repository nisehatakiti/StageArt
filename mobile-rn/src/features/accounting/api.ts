import type { ApiClient } from '@/api/client';
import type { ProductionAccountingSummary } from '@/types/api';

/**
 * GET /productions/{id}/accounting - the Production Accounting Summary
 * Read Model (Backend Phase 6.0). Returns Budget/Actual/Variance for a
 * single Production, computed server-side from Journal Entry (Actual's
 * sole source of truth) and the Production's ACTIVE Budget. This client
 * never re-derives or recomputes any of these figures - see
 * useProductionAccounting.ts.
 */
export function fetchProductionAccounting(client: ApiClient, productionId: string): Promise<ProductionAccountingSummary> {
  return client.get<ProductionAccountingSummary>(`/productions/${productionId}/accounting`);
}
