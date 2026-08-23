<?php

declare(strict_types=1);

namespace StageArt\Application\Project;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Project\ProjectRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/**
 * Reuses OrganizationAuthorizationService as-is (no ProjectAuthorizationService):
 * Project holds OrganizationId directly, so the same
 * Person -> Membership -> Organization -> RoleKey chain used for
 * Organization applies unchanged, keyed on Project's own organizationId.
 * Only an Organization OWNER may create a Project in that Organization
 * (no auto-provisioning here, unlike Organization creation: the
 * Organization and the requester's Membership already exist).
 */
final class CreateProjectUseCase
{
    private ProjectRepositoryInterface $projects;
    private OrganizationAuthorizationService $authorization;

    public function __construct(ProjectRepositoryInterface $projects, OrganizationAuthorizationService $authorization)
    {
        $this->projects = $projects;
        $this->authorization = $authorization;
    }

    public function execute(CreateProjectCommand $command): ProjectResult
    {
        $person = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $person) {
            throw new ProjectAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER])) {
            throw new ProjectAccessDeniedException('Only an Organization Owner can create a Project in this Organization.');
        }

        $project = Project::create($organizationId, $command->name);
        $this->projects->save($project);

        return ProjectResult::fromDomain($project, RoleKey::owner());
    }
}
