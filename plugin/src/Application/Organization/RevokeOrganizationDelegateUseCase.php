<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use InvalidArgumentException;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Role\RoleKey;

/** Owner-only, the inverse of AssignOrganizationDelegateUseCase - demotes an ORGANIZATION_MANAGER back to a plain MEMBER. */
final class RevokeOrganizationDelegateUseCase
{
    private MembershipRepositoryInterface $memberships;
    private OrganizationAuthorizationService $authorization;

    public function __construct(MembershipRepositoryInterface $memberships, OrganizationAuthorizationService $authorization)
    {
        $this->memberships = $memberships;
        $this->authorization = $authorization;
    }

    public function execute(RevokeOrganizationDelegateCommand $command): void
    {
        $executor = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $executor) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $this->authorization->hasRole($executor, $organizationId, [RoleKey::OWNER])) {
            throw new OrganizationAccessDeniedException('Only the Organization Owner can revoke a delegate.');
        }

        $targetPersonId = PersonId::fromString($command->targetPersonId);
        $targetMembership = $this->memberships->findByOrganizationAndPerson($organizationId, $targetPersonId);

        if (! $targetMembership || ! $targetMembership->isActive()) {
            throw new InvalidArgumentException('The target Person must have an ACTIVE Membership in this Organization.');
        }

        if ($targetMembership->roleKey()->toString() !== RoleKey::ORGANIZATION_MANAGER) {
            throw new InvalidArgumentException('The target Person is not currently an Organization delegate.');
        }

        $targetMembership->changeRole(RoleKey::member());
        $this->memberships->save($targetMembership);
    }
}
