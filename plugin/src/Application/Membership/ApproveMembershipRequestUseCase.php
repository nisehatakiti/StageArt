<?php

declare(strict_types=1);

namespace StageArt\Application\Membership;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Membership\DelegateArea;
use StageArt\Domain\Membership\MembershipId;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Person\PersonRepositoryInterface;

/** Membership.md's "Approval": the Organization Owner, or a delegate
 * holding メンバー管理 (DelegateArea::MEMBERS), may approve a REQUESTED
 * Membership into ACTIVE. Authorized against the request's own
 * Organization, so another Organization's request id never passes. */
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

        if (! $approver || ! $this->authorization->hasDelegateArea($approver, $membership->organizationId(), DelegateArea::MEMBERS)) {
            throw new MembershipAccessDeniedException('Only the Organization Owner or a delegate with member management can approve this Membership request.');
        }

        $membership->approve();
        $this->memberships->save($membership);

        return MembershipRequestResult::fromDomain($membership, $this->people->findById($membership->personId()));
    }
}
