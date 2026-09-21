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

/**
 * A full PUT per the existing Backend Update endpoint. `name`/`title_heading`
 * are always applied unconditionally by UpdateProductionUseCase
 * (rename()/changeTitleHeading() both run every call, defaulting to null
 * when the key is missing from the request body) - every caller must
 * pass the Production's current name/titleHeading back, not just the
 * fields it means to change, mirroring updateOrganization()'s identical
 * type/description requirement. `slug`/`published` remain "only touch
 * when explicitly provided". `status` can no longer be changed via this
 * endpoint at all (Phase 6.1 moved it to the dedicated Lifecycle Action
 * endpoints - a `status` key here is rejected with 422) - this function
 * has never sent one and must not start now.
 */
/**
 * StageArt Phase 1 (docs/12-FunctionalStructure.md §20): the five
 * Production Information sections - `description`/`flyerUrl`/
 * `venueName`/`scheduleStartDate`/`scheduleEndDate`/`scriptCredit`/
 * `directionCredit` and each section's own `*PublishedAt` are all
 * optional/trailing. Every field this function is called with replaces
 * the Production's current value (matching `titleHeading`'s existing
 * convention - omitting a key sends it as `undefined`, which the
 * backend then reads as `null`/"clear this field").
 */
export function updateProduction(
  client: ApiClient,
  id: string,
  fields: {
    name: string;
    titleHeading: string | null;
    slug?: string;
    published?: boolean;
    description?: string | null;
    descriptionPublishedAt?: string | null;
    flyerUrl?: string | null;
    flyerPublishedAt?: string | null;
    venueName?: string | null;
    venuePublishedAt?: string | null;
    scheduleStartDate?: string | null;
    scheduleEndDate?: string | null;
    schedulePublishedAt?: string | null;
    scriptCredit?: string | null;
    directionCredit?: string | null;
    scriptDirectionPublishedAt?: string | null;
    memberInfoPublishedAt?: string | null;
    /** Internal-only (never shown on the Public Page). Like `name`, null
     * here REPLACES the current value (it does not mean "leave
     * unchanged") - a caller that wants to keep the existing capacity
     * must send it back explicitly. Changing it cascades server-side to
     * overwrite every existing Performance's own capacity for this
     * Production (UpdateProductionUseCase's own confirmed behavior). */
    capacity?: number | null;
    performanceCommonRemarks?: string | null;
  }
): Promise<Production> {
  return client.put<Production>(`/productions/${id}`, {
    name: fields.name,
    title_heading: fields.titleHeading,
    slug: fields.slug,
    published: fields.published,
    description: fields.description,
    description_published_at: fields.descriptionPublishedAt,
    flyer_url: fields.flyerUrl,
    flyer_published_at: fields.flyerPublishedAt,
    venue_name: fields.venueName,
    venue_published_at: fields.venuePublishedAt,
    schedule_start_date: fields.scheduleStartDate,
    schedule_end_date: fields.scheduleEndDate,
    schedule_published_at: fields.schedulePublishedAt,
    script_credit: fields.scriptCredit,
    direction_credit: fields.directionCredit,
    script_direction_published_at: fields.scriptDirectionPublishedAt,
    member_info_published_at: fields.memberInfoPublishedAt,
    capacity: fields.capacity,
    performance_common_remarks: fields.performanceCommonRemarks,
  });
}

/**
 * The Lifecycle Action endpoints (`PATCH /productions/{id}/{action}`) -
 * Status can no longer be changed via the generic `updateProduction()`
 * PUT above, only through these. `activate` ("公演を確定する", PLANNING ->
 * ACTIVE) also publishes the Production server-side
 * (Production::activate()). `complete` enforces the existing
 * Settlement-completion Guard server-side (CompleteProductionUseCase) -
 * this function does not duplicate that check client-side.
 */
export function activateProduction(client: ApiClient, id: string): Promise<Production> {
  return client.patch<Production>(`/productions/${id}/activate`);
}

export function completeProduction(client: ApiClient, id: string): Promise<Production> {
  return client.patch<Production>(`/productions/${id}/complete`);
}

export function archiveProduction(client: ApiClient, id: string): Promise<Production> {
  return client.patch<Production>(`/productions/${id}/archive`);
}

export function cancelProduction(client: ApiClient, id: string): Promise<Production> {
  return client.patch<Production>(`/productions/${id}/cancel`);
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
