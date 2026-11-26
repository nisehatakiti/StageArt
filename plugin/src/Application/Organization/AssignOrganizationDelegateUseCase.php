<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use InvalidArgumentException;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Role\RoleKey;

/**
 * StageArt 団体管理UI整理 "代理人を設定": per docs/04-DomainModel/
 * Organization.md's "DelegateRole" section ("Organization Ownerという
 * 独立したRole体系は作成しない"), delegation is a Membership::changeRole()
 * call toward RoleKey::ORGANIZATION_MANAGER - no separate Delegate Entity
 * (unlike ProductionDelegate at Production Scope). Owner-only, mirroring
 * OwnerTransferUseCase's own "only the current Owner may execute" rule -
 * an ORGANIZATION_MANAGER must never be able to grant that same power to
 * someone else (no privilege escalation path).
 */
final class AssignOrganizationDelegateUseCase
{
    private MembershipRepositoryInterface $memberships;
    private OrganizationAuthorizationService $authorization;

    public function __construct(MembershipRepositoryInterface $memberships, OrganizationAuthorizationService $authorization)
    {
        $this->memberships = $memberships;
        $this->authorization = $authorization;
    }

    public function execute(AssignOrganizationDelegateCommand $command): void
    {
        $executor = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $executor) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $this->authorization->hasRole($executor, $organizationId, [RoleKey::OWNER])) {
            throw new OrganizationAccessDeniedException('Only the Organization Owner can assign a delegate.');
        }

        $targetPersonId = PersonId::fromString($command->targetPersonId);
        $targetMembership = $this->memberships->findByOrganizationAndPerson($organizationId, $targetPersonId);

        if (! $targetMembership || ! $targetMembership->isActive()) {
            throw new InvalidArgumentException('The target Person must have an ACTIVE Membership in this Organization.');
        }

        if ($targetMembership->roleKey()->toString() === RoleKey::OWNER) {
            throw new InvalidArgumentException('The Organization Owner cannot also be assigned as a delegate - transfer ownership instead.');
        }

        $targetMembership->changeRole(RoleKey::organizationManager());
        $this->memberships->save($targetMembership);
    }
}
