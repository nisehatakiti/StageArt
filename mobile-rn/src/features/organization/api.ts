import type { ApiClient } from '@/api/client';
import { publicGet } from '@/api/publicClient';
import type { Organization, Project, PublicOrganization } from '@/types/api';

/** GET /organizations is Membership-scoped server-side (only ACTIVE
 * Memberships' Organizations are returned - ListOrganizationsUseCase). */
export function fetchOrganizations(client: ApiClient): Promise<Organization[]> {
  return client.get<Organization[]>('/organizations');
}

/**
 * StageArt Web First Phase 2: `slug` is required here (this is the
 * onboarding create call - see CreateOrganizationCommand.php), unlike
 * updateOrganization's slug, which is optional (only sent when the user
 * is actually changing it).
 */
export function createOrganization(client: ApiClient, name: string, slug: string): Promise<Organization> {
  return client.post<Organization>('/organizations', { name, slug });
}

/**
 * A full PUT per the existing Backend Update endpoint (`name`/`status`
 * are always required by UpdateOrganizationUseCase, even when only
 * publishing - see its own docblock); callers must pass the
 * Organization's current name/status back, not just the fields they
 * mean to change.
 */
export function updateOrganization(
  client: ApiClient,
  id: string,
  fields: { name: string; status: string; slug?: string; published?: boolean }
): Promise<Organization> {
  return client.put<Organization>(`/organizations/${id}`, fields);
}

/** GET /organizations/by-slug/{slug} - public, unauthenticated (see
 * src/types/api.ts's PublicOrganization docblock). 404s identically for
 * a nonexistent slug and an existing-but-unpublished Organization. */
export function fetchPublicOrganizationBySlug(slug: string): Promise<PublicOrganization> {
  return publicGet<PublicOrganization>(`/organizations/by-slug/${encodeURIComponent(slug)}`);
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

/**
 * StageArt Web First Phase 2: the onboarding flow calls this
 * transparently between Organization creation and Production creation
 * (Production requires a `project_id`, never an `organization_id`
 * directly - see src/types/api.ts's Project docblock and the Backend
 * Phase 2 plan's §3). `name: null` matches CreateProjectUseCase's own
 * default - the user never sees or names this Project.
 */
export function createProject(client: ApiClient, organizationId: string): Promise<Project> {
  return client.post<Project>('/projects', { organization_id: organizationId, name: null });
}
