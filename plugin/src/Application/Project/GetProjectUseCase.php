<?php

declare(strict_types=1);

namespace StageArt\Application\Project;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Project\ProjectId;
use StageArt\Domain\Project\ProjectRepositoryInterface;

/**
 * Unlike Organization's Get (which can resolve the caller's role from the
 * requested OrganizationId alone, before loading anything), Project's
 * owning Organization is only known once the Project row itself is
 * loaded, so the order here is: load Project -> resolve role from its
 * organizationId -> authorize. A non-existent ProjectId always yields
 * NotFound regardless of the caller's Memberships.
 */
final class GetProjectUseCase
{
    private ProjectRepositoryInterface $projects;
    private OrganizationAuthorizationService $authorization;

    public function __construct(ProjectRepositoryInterface $projects, OrganizationAuthorizationService $authorization)
    {
        $this->projects = $projects;
        $this->authorization = $authorization;
    }

    public function execute(GetProjectQuery $query): ProjectResult
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $person) {
            throw new ProjectAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $project = $this->projects->findById(ProjectId::fromString($query->projectId));

        if (! $project) {
            throw new ProjectNotFoundException($query->projectId);
        }

        $role = $this->authorization->roleFor($person, $project->organizationId());

        if (! $role) {
            throw new ProjectAccessDeniedException('You are not a Member of this Project\'s Organization.');
        }

        return ProjectResult::fromDomain($project, $role);
    }
}
