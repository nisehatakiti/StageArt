<?php

declare(strict_types=1);

namespace StageArt\Application\PublicPage;

use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Project\ProjectRepositoryInterface;

/**
 * An Organization's Productions (all of them, published or not), found the
 * same way the public Organization page finds them: its Projects, then
 * their Productions (GetPublicOrganizationBySlugUseCase).
 */
final class OrganizationProductionsLookup
{
    private ProjectRepositoryInterface $projects;
    private ProductionRepositoryInterface $productions;

    public function __construct(ProjectRepositoryInterface $projects, ProductionRepositoryInterface $productions)
    {
        $this->projects = $projects;
        $this->productions = $productions;
    }

    /** @return list<Production> */
    public function forOrganization(string $organizationId): array
    {
        $projects = $this->projects->findByOrganizationIds([OrganizationId::fromString($organizationId)]);

        if ($projects === []) {
            return [];
        }

        return array_values($this->productions->findByProjectIds(array_map(static fn ($project) => $project->id(), $projects)));
    }
}
