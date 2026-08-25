<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Project\ProjectRepositoryInterface;

/** Public, unauthenticated search (公演・活動検索). Resolves each match's
 * parent Organization the same way GetPublicProductionBySlugUseCase does
 * (Production -> Project -> Organization); a match whose parent can't be
 * resolved is silently skipped rather than erroring the whole search. */
final class SearchProductionsUseCase
{
    private const LIMIT = 20;

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

    /**
     * @return PublicProductionResult[]
     */
    public function execute(SearchProductionsQuery $query): array
    {
        $trimmed = trim($query->query);

        if ($trimmed === '') {
            return [];
        }

        $matches = $this->productions->searchPublished($trimmed, self::LIMIT);

        if ($matches === []) {
            return [];
        }

        $results = [];
        foreach ($matches as $production) {
            $project = $this->projects->findById($production->projectId());

            if ($project === null) {
                continue;
            }

            $organization = $this->organizations->findById($project->organizationId());

            if ($organization === null || $organization->slug() === null) {
                continue;
            }

            $results[] = PublicProductionResult::fromDomain($production, $organization);
        }

        return $results;
    }
}
