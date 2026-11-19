<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use InvalidArgumentException;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Role\RoleKey;

/**
 * 代理人設定 (docs/03-PublicPageURLAndPublicationSchedule.md「代理人設定」):
 * replaces the full set of 管理領域 one delegate holds. Owner-only, the
 * same rule as making someone a delegate in the first place
 * (AssignOrganizationDelegateUseCase) - a delegate can never widen their
 * own areas or anyone else's, whatever areas they hold.
 *
 * The target must already be an ACTIVE delegate (ORGANIZATION_MANAGER)
 * of this Organization; unknown or repeated areas are refused (see
 * DelegateArea::normalize()). Nothing in the request is trusted for the
 * decision itself - both sides are read from the stored Memberships.
 */
final class UpdateOrganizationDelegateAreasUseCase
{
    private MembershipRepositoryInterface $memberships;
    private OrganizationAuthorizationService $authorization;

    public function __construct(MembershipRepositoryInterface $memberships, OrganizationAuthorizationService $authorization)
    {
        $this->memberships = $memberships;
        $this->authorization = $authorization;
    }

    public function execute(UpdateOrganizationDelegateAreasCommand $command): OrganizationDelegateAreasResult
    {
        $executor = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $executor) {
            throw new OrganizationAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $organizationId = OrganizationId::fromString($command->organizationId);

        if (! $this->authorization->hasRole($executor, $organizationId, [RoleKey::OWNER])) {
            throw new OrganizationAccessDeniedException('Only the Organization Owner can change delegate areas.');
        }

        $targetMembership = $this->memberships->findByOrganizationAndPerson($organizationId, PersonId::fromString($command->targetPersonId));

        if (! $targetMembership || ! $targetMembership->isActive()) {
            throw new InvalidArgumentException('The target Person must have an ACTIVE Membership in this Organization.');
        }

        if ($targetMembership->roleKey()->toString() !== RoleKey::ORGANIZATION_MANAGER) {
            throw new InvalidArgumentException('The target Person is not currently an Organization delegate.');
        }

        $targetMembership->replaceDelegateAreas($command->areas);
        $this->memberships->save($targetMembership);

        return OrganizationDelegateAreasResult::fromDomain($targetMembership);
    }
}
