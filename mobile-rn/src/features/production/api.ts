import type { ApiClient } from '@/api/client';
import { publicGet } from '@/api/publicClient';
import type { CurrentPerson, Production, PublicProduction } from '@/types/api';

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

/**
 * StageArt Web First Phase 2: `primaryManagerPersonId` is always the
 * caller's own Person ID in the onboarding flow (the Organization Owner
 * who just created the Production - see CreateProductionUseCase's
 * eligibility check, which requires the PrimaryManager to already hold
 * a Membership in the Production's Organization).
 */
export function createProduction(
  client: ApiClient,
  projectId: string,
  name: string,
  slug: string,
  primaryManagerPersonId: string
): Promise<Production> {
  return client.post<Production>('/productions', {
    project_id: projectId,
    name,
    slug,
    primary_manager_person_id: primaryManagerPersonId,
  });
}

/** A full PUT per the existing Backend Update endpoint (`name` is
 * always required by UpdateProductionUseCase, even when only publishing
 * - see its own docblock, which unlike Organization's never touches
 * `status`). */
export function updateProduction(
  client: ApiClient,
  id: string,
  fields: { name: string; slug?: string; published?: boolean }
): Promise<Production> {
  return client.put<Production>(`/productions/${id}`, fields);
}

/** GET /productions/by-slug/{slug} - public, unauthenticated (see
 * src/types/api.ts's PublicProduction docblock). 404s identically for a
 * nonexistent slug and an existing-but-unpublished Production. */
export function fetchPublicProductionBySlug(slug: string): Promise<PublicProduction> {
  return publicGet<PublicProduction>(`/productions/by-slug/${encodeURIComponent(slug)}`);
}

/** Public, unauthenticated search (公演・活動検索) - published Productions
 * whose name contains `query`. */
export function searchPublicProductions(query: string): Promise<PublicProduction[]> {
  return publicGet<PublicProduction[]>(`/productions/search?q=${encodeURIComponent(query)}`);
}
