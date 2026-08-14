<?php

declare(strict_types=1);

namespace StageArt\Application\Project;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Project\ProjectRepositoryInterface;
use StageArt\Domain\Project\ProjectStatus;

/**
 * Scope is structural, same principle as ListOrganizationsUseCase: the
 * candidate OrganizationIds come only from the caller's own ACTIVE
 * Memberships, so a Project under an Organization the caller doesn't
 * belong to can never appear in the result set. ARCHIVED Projects are
 * excluded, so an archived Project disappears from this list.
 */
final class ListProjectsUseCase
{
    private ProjectRepositoryInterface $projects;
    private MembershipRepositoryInterface $memberships;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        ProjectRepositoryInterface $projects,
        MembershipRepositoryInterface $memberships,
        OrganizationAuthorizationService $authorization
    ) {
        $this->projects = $projects;
        $this->memberships = $memberships;
        $this->authorization = $authorization;
    }

    /**
     * @return ProjectResult[]
     */
    public function execute(ListProjectsForPersonQuery $query): array
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);

        if (! $person) {
            return [];
        }

        $activeMemberships = array_values(array_filter(
            $this->memberships->findByPersonId($person->id()),
            static fn (Membership $membership): bool => $membership->isActive()
        ));

        if ($activeMemberships === []) {
            return [];
        }

        $organizationIds = array_map(
            static fn (Membership $membership) => $membership->organizationId(),
            $activeMemberships
        );

        $roleByOrganizationId = [];
        foreach ($activeMemberships as $membership) {
            $roleByOrganizationId[$membership->organizationId()->toString()] = $membership->roleKey();
        }

        $projects = array_values(array_filter(
            $this->projects->findByOrganizationIds($organizationIds),
            static fn (Project $project): bool => ! $project->status()->equals(
                ProjectStatus::fromString(ProjectStatus::ARCHIVED)
            )
        ));

        return array_map(
            static fn (Project $project): ProjectResult => ProjectResult::fromDomain(
                $project,
                $roleByOrganizationId[$project->organizationId()->toString()]
            ),
            $projects
        );
    }
}
