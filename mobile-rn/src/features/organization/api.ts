import type { ApiClient } from '@/api/client';
import type { Organization, Project } from '@/types/api';

/** GET /organizations is Membership-scoped server-side (only ACTIVE
 * Memberships' Organizations are returned - ListOrganizationsUseCase). */
export function fetchOrganizations(client: ApiClient): Promise<Organization[]> {
  return client.get<Organization[]>('/organizations');
}

/**
 * Project is an internal bridge Domain used only to resolve which
 * Organization a Production belongs to (see src/types/api.ts). GET
 * /projects is likewise Membership-scoped server-side and, like GET
 * /productions, has no organization_id filter query param - there is no
 * Organization-scoped variant of either endpoint today (confirmed by
 * reading ProjectRestController.php / ProductionRestController.php: no
 * such Business Rule change was made, this is Open Item disclosure, not
 * a new API).
 */
export function fetchProjects(client: ApiClient): Promise<Project[]> {
  return client.get<Project[]>('/projects');
}
