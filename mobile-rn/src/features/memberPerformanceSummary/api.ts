import type { ApiClient } from '@/api/client';
import type { MemberPerformanceSummary } from '@/types/api';

/** GET /productions/{id}/member-performance-summary - Phase 5 §9. */
export function fetchMemberPerformanceSummary(client: ApiClient, productionId: string): Promise<MemberPerformanceSummary> {
  return client.get<MemberPerformanceSummary>(`/productions/${productionId}/member-performance-summary`);
}
