<?php

declare(strict_types=1);

namespace StageArt\Domain\Project;

use StageArt\Domain\Organization\OrganizationId;

interface ProjectRepositoryInterface
{
    public function save(Project $project): void;

    public function findById(ProjectId $id): ?Project;

    /**
     * @param OrganizationId[] $organizationIds
     * @return Project[]
     */
    public function findByOrganizationIds(array $organizationIds): array;
}
