<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Role\RoleKey;

/**
 * 代理人設定: every current delegate's 管理領域, for the Owner's 代理人設定
 * table. Owner-only, like setting them (UpdateOrganizationDelegateAreasUseCase) -
 * a delegate sees only their own areas, through OrganizationResult's
 * current_person_management_areas.
 */
final class ListOrganizationDelegateAreasUseCase
{
    private MembershipRepositoryInterface $memberships;
    private OrganizationAuthorizationService $authorization;

    public function __construct(MembershipRepositoryInterface $memberships, OrganizationAuthorizationService $authorization)
    {
        $this->memberships = $memberships;
        $this->authorization = $authorization;
    }

    /**
     * @return OrganizationDelegateAreasResult[]
     */
    public function execute(ListOrganizationDelegateAreasQuery $query): array
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);
        $organizationId = OrganizationId::fromString($query->organizationId);

        if (! $person || ! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER])) {
            throw new OrganizationAccessDeniedException('Only the Organization Owner can view delegate areas.');
        }

        $delegates = array_filter(
            $this->memberships->findByOrganizationId($organizationId),
            static fn (Membership $membership): bool => $membership->isActive()
                && $membership->roleKey()->toString() === RoleKey::ORGANIZATION_MANAGER
        );

        return array_values(array_map(
            static fn (Membership $membership): OrganizationDelegateAreasResult => OrganizationDelegateAreasResult::fromDomain($membership),
            $delegates
        ));
    }
}
