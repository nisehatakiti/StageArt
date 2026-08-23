import type { ApiClient } from '@/api/client';
import type { MyDashboard } from '@/types/api';

/**
 * GET /me/dashboard (Backend Phase 7.3). No parameters: the Backend
 * always resolves the caller's own StageArt Person from the
 * authenticated request, so there is no `person_id` (or any other
 * Production/Organization-scoping) parameter to pass here - see
 * DashboardRestController.php's own docblock for the same guarantee on
 * the server side.
 */
export function fetchMyDashboard(client: ApiClient): Promise<MyDashboard> {
  return client.get<MyDashboard>('/me/dashboard');
}
