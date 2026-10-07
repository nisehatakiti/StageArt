<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Application\Notification\PersonEmailResolver;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/**
 * StageArt 団体管理UI整理: the "メンバー管理" roster, needed by that screen
 * itself and by the 代理人を設定/代表者交代 screens' member picker. Scoped to
 * OWNER/ORGANIZATION_MANAGER (the same pair every other メンバー管理 action
 * in this round is scoped to) - this is management data (every member's
 * current Role), not Organization Public Profile data.
 */
final class ListOrganizationMembersUseCase
{
    private MembershipRepositoryInterface $memberships;
    private PersonRepositoryInterface $people;
    private OrganizationAuthorizationService $authorization;
    private PersonEmailResolver $personEmailResolver;

    public function __construct(
        MembershipRepositoryInterface $memberships,
        PersonRepositoryInterface $people,
        OrganizationAuthorizationService $authorization,
        PersonEmailResolver $personEmailResolver
    ) {
        $this->memberships = $memberships;
        $this->people = $people;
        $this->authorization = $authorization;
        $this->personEmailResolver = $personEmailResolver;
    }

    /**
     * @return OrganizationMemberResult[]
     */
    public function execute(ListOrganizationMembersQuery $query): array
    {
        $person = $this->authorization->resolveCurrentPerson($query->requestedByWordPressUserId);
        $organizationId = OrganizationId::fromString($query->organizationId);

        if (! $person || ! $this->authorization->hasRole($person, $organizationId, [RoleKey::OWNER, RoleKey::ORGANIZATION_MANAGER])) {
            throw new OrganizationAccessDeniedException('Only an Organization Owner or delegate can view the member list.');
        }

        $active = array_filter(
            $this->memberships->findByOrganizationId($organizationId),
            static fn (Membership $membership): bool => $membership->isActive()
        );

        return array_values(array_map(
            fn (Membership $membership) => OrganizationMemberResult::fromDomain(
                $membership,
                $this->people->findById($membership->personId()),
                $this->personEmailResolver->resolve($membership->personId())
            ),
            $active
        ));
    }
}
