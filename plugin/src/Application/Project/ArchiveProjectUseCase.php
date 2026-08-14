<?php

declare(strict_types=1);

namespace StageArt\Application\Project;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Membership\RoleKey;
use StageArt\Domain\Project\ProjectId;
use StageArt\Domain\Project\ProjectRepositoryInterface;

/**
 * DELETE is always an archive, never a physical delete: Project.md states
 * "Projectは原則として物理削除しない" (same wording as Organization.md),
 * and Production/History under a Project must survive regardless.
 */
final class ArchiveProjectUseCase
{
    private ProjectRepositoryInterface $projects;
    private OrganizationAuthorizationService $authorization;

    public function __construct(ProjectRepositoryInterface $projects, OrganizationAuthorizationService $authorization)
    {
        $this->projects = $projects;
        $this->authorization = $authorization;
    }

    public function execute(ArchiveProjectCommand $command): void
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new ProjectAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $project = $this->projects->findById(ProjectId::fromString($command->projectId));

        if (! $project) {
            throw new ProjectNotFoundException($command->projectId);
        }

        if (! $this->authorization->hasRole($person, $project->organizationId(), [RoleKey::OWNER])) {
            throw new ProjectAccessDeniedException('Only an Organization Owner can archive this Project.');
        }

        $project->archive();
        $this->projects->save($project);
    }
}
