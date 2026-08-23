<?php

declare(strict_types=1);

namespace StageArt\Application\Project;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Project\ProjectId;
use StageArt\Domain\Project\ProjectRepositoryInterface;
use StageArt\Domain\Project\ProjectStatus;
use StageArt\Domain\Role\RoleKey;

/**
 * Status transitions are not order-validated in this slice (Project.md
 * defines DRAFT/ACTIVE/CLOSED/ARCHIVED without mandating a sequence), so
 * any valid ProjectStatus value is accepted here as a direct update.
 */
final class UpdateProjectUseCase
{
    private ProjectRepositoryInterface $projects;
    private OrganizationAuthorizationService $authorization;

    public function __construct(ProjectRepositoryInterface $projects, OrganizationAuthorizationService $authorization)
    {
        $this->projects = $projects;
        $this->authorization = $authorization;
    }

    public function execute(UpdateProjectCommand $command): ProjectResult
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
            throw new ProjectAccessDeniedException('Only an Organization Owner can update this Project.');
        }

        $project->rename($command->name);
        $project->changeStatus(ProjectStatus::fromString($command->status));

        $this->projects->save($project);

        return ProjectResult::fromDomain($project, RoleKey::owner());
    }
}
