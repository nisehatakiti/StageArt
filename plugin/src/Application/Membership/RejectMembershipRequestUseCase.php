<?php

declare(strict_types=1);

namespace StageArt\Application\Membership;

use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Domain\Membership\DelegateArea;
use StageArt\Domain\Membership\MembershipId;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Person\PersonRepositoryInterface;

/** Same gate as ApproveMembershipRequestUseCase: the Owner or a
 * メンバー管理 delegate of the request's own Organization. */
final class RejectMembershipRequestUseCase
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

    public function execute(RejectMembershipRequestCommand $command): MembershipRequestResult
    {
        $membership = $this->memberships->findById(MembershipId::fromString($command->membershipId));

        if (! $membership) {
            throw new MembershipRequestNotFoundException($command->membershipId);
        }

        $approver = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $approver || ! $this->authorization->hasDelegateArea($approver, $membership->organizationId(), DelegateArea::MEMBERS)) {
            throw new MembershipAccessDeniedException('Only the Organization Owner or a delegate with member management can reject this Membership request.');
        }

        $membership->reject();
        $this->memberships->save($membership);

        return MembershipRequestResult::fromDomain($membership, $this->people->findById($membership->personId()));
    }
}
