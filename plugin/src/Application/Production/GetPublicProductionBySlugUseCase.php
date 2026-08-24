<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Project\ProjectRepositoryInterface;

/**
 * StageArt Web First Phase 2: no permission check by design - the
 * public, unauthenticated `stageart.top/{organization-slug}/
 * {production-slug}` read path. Resolves the parent Organization via
 * Production -> Project -> Organization (Production has no direct
 * organizationId - see this Phase's report). Not-found and exists-but-
 * unpublished both throw the identical ProductionNotFoundException.
 * A Production's own `publishedAt` is the sole visibility gate here,
 * independent of its Organization's own publication state - the two
 * are deliberately not cross-linked (see this Phase's report on
 * publication state design).
 */
final class GetPublicProductionBySlugUseCase
{
    private ProductionRepositoryInterface $productions;
    private ProjectRepositoryInterface $projects;
    private OrganizationRepositoryInterface $organizations;

    public function __construct(
        ProductionRepositoryInterface $productions,
        ProjectRepositoryInterface $projects,
        OrganizationRepositoryInterface $organizations
    ) {
        $this->productions = $productions;
        $this->projects = $projects;
        $this->organizations = $organizations;
    }

    public function execute(GetPublicProductionBySlugQuery $query): PublicProductionResult
    {
        $production = $this->productions->findBySlug($query->slug);

        if ($production === null || ! $production->isPublished()) {
            throw new ProductionNotFoundException($query->slug);
        }

        $project = $this->projects->findById($production->projectId());

        if ($project === null) {
            throw new ProductionNotFoundException($query->slug);
        }

        $organization = $this->organizations->findById($project->organizationId());

        if ($organization === null || $organization->slug() === null) {
            throw new ProductionNotFoundException($query->slug);
        }

        return PublicProductionResult::fromDomain($production, $organization);
    }
}
