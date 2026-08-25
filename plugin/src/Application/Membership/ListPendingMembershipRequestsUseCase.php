<?php

declare(strict_types=1);

namespace StageArt\Application\Membership;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/** docs/04-HomeRoleBasedMenu.md's "Organization参加申請（3件）" - the
 * Organization Owner's approval queue. */
final class ListPendingMembershipRequestsUseCase
{
    private MembershipRepositoryInterface $memberships;
    private PersonRepositoryInterface $people;
    private OrganizationAuthorizationService $authorization;

    public function __construct(
        MembershipRepositoryInterface $memberships,
        PersonRepositoryInterface $people,
        OrganizationAuthorizationService $authorization
    ) {
        $this->memberships = $memberships;
        $this->people = $people;
        $this->authorization = $authorization;
    }

    /**
     * @return MembershipRequestResult[]
     */
    public function execute(ListPendingMembershipRequestsQuery $query): array
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);
        $organizationId = OrganizationId::fromString($query->organizationId);

        if (! $person || ! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER])) {
            throw new MembershipAccessDeniedException('Only an Organization Owner can view pending Membership requests.');
        }

        $pending = array_filter(
            $this->memberships->findByOrganizationId($organizationId),
            static fn (Membership $membership): bool => $membership->isPending()
        );

        return array_values(array_map(
            fn (Membership $membership) => MembershipRequestResult::fromDomain($membership, $this->people->findById($membership->personId())),
            $pending
        ));
    }
}
