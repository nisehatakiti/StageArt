<?php

declare(strict_types=1);

namespace StageArt\Application\Membership;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Membership\MembershipId;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/** Membership.md's "Approval": only an Organization Owner may approve a
 * REQUESTED Membership into ACTIVE. */
final class ApproveMembershipRequestUseCase
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

    public function execute(ApproveMembershipRequestCommand $command): MembershipRequestResult
    {
        $membership = $this->memberships->findById(MembershipId::fromString($command->membershipId));

        if (! $membership) {
            throw new MembershipRequestNotFoundException($command->membershipId);
        }

        $approver = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $approver || ! $this->authorization->hasRole($approver, $membership->organizationId(), [RoleKey::OWNER])) {
            throw new MembershipAccessDeniedException('Only an Organization Owner can approve this Membership request.');
        }

        $membership->approve();
        $this->memberships->save($membership);

        return MembershipRequestResult::fromDomain($membership, $this->people->findById($membership->personId()));
    }
}
