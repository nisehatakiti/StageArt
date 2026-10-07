<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Organization\OrganizationExternalLinkRepositoryInterface;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Organization\OrganizationSNS;
use StageArt\Domain\Organization\OrganizationSNSRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Project\ProjectRepositoryInterface;

/**
 * StageArt Web First Phase 2 / Public Page Architecture phase: no
 * permission check by design - this is the public, unauthenticated
 * `stageart.top/{organization-slug}` read path. Not-found and
 * exists-but-unpublished both throw the identical
 * OrganizationNotFoundException, so an unpublished Organization's mere
 * existence is never distinguishable from a slug that was never
 * registered at all.
 *
 * Also resolves this Organization's published Productions
 * (Organization -> Project -> Production, since Production has no
 * direct organizationId - see ProductionSlug.php) for the Public Page's
 * "開催予定・公開中の公演" / "過去の公演" sections. Unpublished
 * Productions are filtered out here, the same way the Organization
 * itself is - the public page must never reveal a draft Production's
 * existence via its parent's page either.
 */
final class GetPublicOrganizationBySlugUseCase
{
    private OrganizationRepositoryInterface $organizations;
    private ProjectRepositoryInterface $projects;
    private ProductionRepositoryInterface $productions;
    private OrganizationSNSRepositoryInterface $sns;
    private OrganizationExternalLinkRepositoryInterface $externalLinks;

    public function __construct(
        OrganizationRepositoryInterface $organizations,
        ProjectRepositoryInterface $projects,
        ProductionRepositoryInterface $productions,
        OrganizationSNSRepositoryInterface $sns,
        OrganizationExternalLinkRepositoryInterface $externalLinks
    ) {
        $this->organizations = $organizations;
        $this->projects = $projects;
        $this->productions = $productions;
        $this->sns = $sns;
        $this->externalLinks = $externalLinks;
    }

    public function execute(GetPublicOrganizationBySlugQuery $query): PublicOrganizationResult
    {
        $organization = $this->organizations->findBySlug($query->slug);

        if ($organization === null || ! $organization->isPublished()) {
            throw new OrganizationNotFoundException($query->slug);
        }

        $projects = $this->projects->findByOrganizationIds([$organization->id()]);
        $projectIds = array_map(static fn ($project) => $project->id(), $projects);
        $productions = $this->productions->findByProjectIds($projectIds);

        $publishedProductions = array_values(array_filter(
            $productions,
            static fn ($production) => $production->isPublished()
        ));

        $summaries = array_map(
            static fn ($production) => PublicOrganizationProductionSummary::fromDomain($production),
            $publishedProductions
        );

        $visibleSns = array_values(array_filter(
            $this->sns->findByOrganizationId($organization->id()),
            static fn (OrganizationSNS $sns): bool => $sns->isVisible()
        ));
        $snsResults = array_map(
            static fn (OrganizationSNS $sns) => OrganizationSNSResult::fromDomain($sns),
            $visibleSns
        );

        $externalLinkResults = array_map(
            static fn ($link) => OrganizationExternalLinkResult::fromDomain($link),
            $this->externalLinks->findByOrganizationId($organization->id())
        );

        return PublicOrganizationResult::fromDomain($organization, $summaries, $snsResults, $externalLinkResults);
    }
}
