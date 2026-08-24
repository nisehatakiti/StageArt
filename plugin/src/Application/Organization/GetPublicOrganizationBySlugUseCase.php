<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Organization\OrganizationRepositoryInterface;

/**
 * StageArt Web First Phase 2: no permission check by design - this is
 * the public, unauthenticated `stageart.top/{organization-slug}` read
 * path. Not-found and exists-but-unpublished both throw the identical
 * OrganizationNotFoundException, so an unpublished Organization's mere
 * existence is never distinguishable from a slug that was never
 * registered at all.
 */
final class GetPublicOrganizationBySlugUseCase
{
    private OrganizationRepositoryInterface $organizations;

    public function __construct(OrganizationRepositoryInterface $organizations)
    {
        $this->organizations = $organizations;
    }

    public function execute(GetPublicOrganizationBySlugQuery $query): PublicOrganizationResult
    {
        $organization = $this->organizations->findBySlug($query->slug);

        if ($organization === null || ! $organization->isPublished()) {
            throw new OrganizationNotFoundException($query->slug);
        }

        return PublicOrganizationResult::fromDomain($organization);
    }
}
