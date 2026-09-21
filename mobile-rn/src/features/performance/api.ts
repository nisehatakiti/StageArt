import type { ApiClient } from '@/api/client';
import { publicGet } from '@/api/publicClient';
import type { Performance, PublicPerformance } from '@/types/api';

/** GET /productions/{id}/performances (Backend's ListPerformancesUseCase). */
export function fetchPerformances(client: ApiClient, productionId: string): Promise<Performance[]> {
  return client.get<Performance[]>(`/productions/${productionId}/performances`);
}

/** GET /productions/{id}/public-performances - Phase 3 Ticket/Reservation
 * 基盤 §33: unauthenticated, the Public Page's own Performance listing a
 * visitor picks from before reserving a Ticket. Excludes CANCELLED
 * Performances and never carries `capacity` (§34). */
export function fetchPublicPerformances(productionId: string): Promise<PublicPerformance[]> {
  return publicGet<PublicPerformance[]>(`/productions/${productionId}/public-performances`);
}

/** GET /performances/{id}. */
export function fetchPerformance(client: ApiClient, performanceId: string): Promise<Performance> {
  return client.get<Performance>(`/performances/${performanceId}`);
}

/**
 * POST /productions/{id}/performances - creates a Performance (starts at
 * PUBLISHED - StageArt does not use DRAFT to gate public/private
 * visibility). Omitting `capacity` inherits the parent Production's own
 * capacity server-side (CreatePerformanceUseCase.php §10) - this Client
 * never resolves that default itself.
 */
export function createPerformance(
  client: ApiClient,
  productionId: string,
  fields: {
    performanceDate: string;
    startTime: string;
    endTime?: string;
    capacity?: number;
    remarks?: string;
    symbol?: string;
  }
): Promise<Performance> {
  return client.post<Performance>(`/productions/${productionId}/performances`, {
    performance_date: fields.performanceDate,
    start_time: fields.startTime,
    end_time: fields.endTime,
    capacity: fields.capacity,
    remarks: fields.remarks,
    symbol: fields.symbol,
  });
}

/** PUT /performances/{id} - every field here is applied unconditionally
 * (no per-field "only if provided" merge), matching updateRehearsal()'s
 * identical contract - callers must pass the Performance's current
 * capacity/remarks/symbol back unchanged if they don't mean to clear
 * them. `status` is optional - omitting it leaves Status unchanged. */
export function updatePerformance(
  client: ApiClient,
  performanceId: string,
  fields: {
    performanceDate: string;
    startTime: string;
    endTime: string | null;
    capacity: number;
    remarks: string | null;
    symbol: string | null;
    status?: string;
  }
): Promise<Performance> {
  return client.put<Performance>(`/performances/${performanceId}`, {
    performance_date: fields.performanceDate,
    start_time: fields.startTime,
    end_time: fields.endTime,
    capacity: fields.capacity,
    remarks: fields.remarks,
    symbol: fields.symbol,
    status: fields.status,
  });
}

/** POST /performances/{id}/cancel - "中止する". Soft Status-only
 * transition to CANCELLED; the record is never physically deleted and
 * keeps appearing in the Performance list with its CANCELLED status. */
export function cancelPerformance(client: ApiClient, performanceId: string): Promise<Performance> {
  return client.post<Performance>(`/performances/${performanceId}/cancel`);
}
