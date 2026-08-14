<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Project\ProjectId;
use StageArt\Domain\Project\ProjectRepositoryInterface;

final class InMemoryProjectRepository implements ProjectRepositoryInterface
{
    /** @var array<string, Project> */
    private array $projects = [];

    public function save(Project $project): void
    {
        $this->projects[$project->id()->toString()] = $project;
    }

    public function findById(ProjectId $id): ?Project
    {
        return $this->projects[$id->toString()] ?? null;
    }

    public function findByOrganizationIds(array $organizationIds): array
    {
        $ids = array_map(static fn (OrganizationId $id): string => $id->toString(), $organizationIds);

        return array_values(array_filter(
            $this->projects,
            static fn (Project $project): bool => in_array($project->organizationId()->toString(), $ids, true)
        ));
    }
}
