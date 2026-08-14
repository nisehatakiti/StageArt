<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;
use StageArt\Domain\Organization\OrganizationStatus;

/**
 * Scope is structural, not a filter applied after the fact: the list of
 * candidate OrganizationIds comes only from the caller's own ACTIVE
 * Memberships, so an Organization the caller doesn't belong to can never
 * appear in the result set. ARCHIVED Organizations (see
 * DeleteOrganizationUseCase) are also excluded, so a deleted Organization
 * disappears from this list.
 */
final class ListOrganizationsUseCase
{
    private OrganizationRepositoryInterface $organizations;
    private MembershipRepositoryInterface $memberships;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        OrganizationRepositoryInterface $organizations,
        MembershipRepositoryInterface $memberships,
        OrganizationAuthorizationService $authorization
    ) {
        $this->organizations = $organizations;
        $this->memberships = $memberships;
        $this->authorization = $authorization;
    }

    /**
     * @return OrganizationResult[]
     */
    public function execute(ListOrganizationsForPersonQuery $query): array
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

        $organizations = array_values(array_filter(
            $this->organizations->findByIds($organizationIds),
            static fn (Organization $organization): bool => ! $organization->status()->equals(
                OrganizationStatus::fromString(OrganizationStatus::ARCHIVED)
            )
        ));

        return array_map(
            static fn (Organization $organization): OrganizationResult => OrganizationResult::fromDomain(
                $organization,
                $roleByOrganizationId[$organization->id()->toString()]
            ),
            $organizations
        );
    }
}
