<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Membership\RoleKey;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * Owner Transfer is deliberately not a generic Role-change endpoint: it
 * is the only Application operation permitted to move a Membership
 * toward or away from RoleKey::OWNER, and it enforces invariants a plain
 * Role change never would:
 *
 *  - exactly one ACTIVE OWNER Membership must exist before the transfer
 *    starts (a 0 or 2+ count means the Organization's data is already in
 *    an invalid state, and this Use Case refuses to silently "fix" it -
 *    see OrganizationOwnerInvariantViolatedException);
 *  - the incoming Owner must already hold an ACTIVE Membership in this
 *    Organization (Owner Transfer never creates one - adding someone to
 *    an Organization and transferring ownership to them are two
 *    separate operations) and must have an ACTIVE UserAccount (an Owner
 *    who cannot authenticate as themselves is not a usable Owner);
 *  - only the current Owner may execute a transfer.
 *
 * The old Owner is demoted to MEMBER and gains no other Role
 * automatically - if they need continued management access, the new
 * Owner grants it separately, exactly like any other Role assignment.
 */
final class OwnerTransferUseCase
{
    private MembershipRepositoryInterface $memberships;
    private UserAccountRepositoryInterface $userAccounts;
    private OrganizationAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        MembershipRepositoryInterface $memberships,
        UserAccountRepositoryInterface $userAccounts,
        OrganizationAuthorizationService $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->memberships = $memberships;
        $this->userAccounts = $userAccounts;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(OwnerTransferCommand $command): void
    {
        $executor = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $executor) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $this->authorization->hasRole($executor, $organizationId, [RoleKey::OWNER])) {
            throw new OrganizationAccessDeniedException('Only the current Organization Owner can transfer ownership.');
        }

        $newOwnerPersonId = PersonId::fromString($command->newOwnerPersonId);

        $this->transactions->run(function () use ($organizationId, $newOwnerPersonId): void {
            $allMemberships = $this->memberships->findByOrganizationId($organizationId);

            $activeOwnerMemberships = array_values(array_filter(
                $allMemberships,
                static fn (Membership $membership): bool => $membership->isActive()
                    && $membership->roleKey()->toString() === RoleKey::OWNER
            ));

            if (count($activeOwnerMemberships) !== 1) {
                throw new OrganizationOwnerInvariantViolatedException(
                    'Expected exactly one ACTIVE Owner Membership, found ' . count($activeOwnerMemberships) . '.'
                );
            }

            $currentOwnerMembership = $activeOwnerMemberships[0];

            if ($currentOwnerMembership->personId()->equals($newOwnerPersonId)) {
                throw new OwnerTransferTargetNotEligibleException('The new Owner is already the current Owner.');
            }

            $newOwnerMembership = null;

            foreach ($allMemberships as $membership) {
                if ($membership->isActive() && $membership->personId()->equals($newOwnerPersonId)) {
                    $newOwnerMembership = $membership;
                    break;
                }
            }

            if (! $newOwnerMembership) {
                throw new OwnerTransferTargetNotEligibleException(
                    'The new Owner must already have an ACTIVE Membership in this Organization.'
                );
            }

            $newOwnerUserAccount = $this->userAccounts->findByPersonId($newOwnerPersonId);

            if (! $newOwnerUserAccount || ! $newOwnerUserAccount->isActive()) {
                throw new OwnerTransferTargetNotEligibleException('The new Owner must have an ACTIVE UserAccount.');
            }

            $currentOwnerMembership->changeRole(RoleKey::member());
            $newOwnerMembership->changeRole(RoleKey::owner());

            $this->memberships->save($currentOwnerMembership);
            $this->memberships->save($newOwnerMembership);
        });
    }
}
