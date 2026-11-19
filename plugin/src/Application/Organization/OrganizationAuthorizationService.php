<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

use StageArt\Domain\Membership\DelegateArea;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

/**
 * Single source of truth for Organization Scope decisions: resolves
 * WordPress User -> Person -> Membership -> RoleKey per the
 * Person/Membership/Organization/Role chain in Authorization.md. Being
 * authenticated (a valid WordPress user) is necessary but never
 * sufficient on its own; every access decision goes through here.
 */
final class OrganizationAuthorizationService
{
    private PersonRepositoryInterface $people;
    private MembershipRepositoryInterface $memberships;

    public function __construct(PersonRepositoryInterface $people, MembershipRepositoryInterface $memberships)
    {
        $this->people = $people;
        $this->memberships = $memberships;
    }

    public function resolveCurrentPerson(int $wordPressUserId): ?Person
    {
        return $this->people->findByWordPressUserId($wordPressUserId);
    }

    public function roleFor(Person $person, OrganizationId $organizationId): ?RoleKey
    {
        $membership = $this->memberships->findByOrganizationAndPerson($organizationId, $person->id());

        if (! $membership || ! $membership->isActive()) {
            return null;
        }

        return $membership->roleKey();
    }

    /**
     * @param string[] $allowedRoleKeys
     */
    public function hasRole(Person $person, OrganizationId $organizationId, array $allowedRoleKeys): bool
    {
        $role = $this->roleFor($person, $organizationId);

        return $role !== null && in_array($role->toString(), $allowedRoleKeys, true);
    }

    /**
     * 代理人の管理領域: may this Person act in `$area` (a DelegateArea
     * value) of this Organization? The Owner always may; a delegate
     * (ORGANIZATION_MANAGER) only when the Owner delegated that area to
     * them; anyone else - a plain MEMBER, an inactive Membership, an
     * outsider - never. Decided from the stored Membership only.
     */
    public function hasDelegateArea(Person $person, OrganizationId $organizationId, string $area): bool
    {
        return in_array($area, $this->managementAreasFor($person, $organizationId), true);
    }

    /**
     * The management areas this Person holds in this Organization: every
     * area for the Owner, the delegated ones for a delegate, none
     * otherwise.
     *
     * @return string[] DelegateArea values, display order
     */
    public function managementAreasFor(Person $person, OrganizationId $organizationId): array
    {
        $membership = $this->memberships->findByOrganizationAndPerson($organizationId, $person->id());

        if (! $membership || ! $membership->isActive()) {
            return [];
        }

        return self::managementAreasOf($membership);
    }

    /** @return string[] */
    public static function managementAreasOf(Membership $membership): array
    {
        return match ($membership->roleKey()->toString()) {
            RoleKey::OWNER => DelegateArea::all(),
            RoleKey::ORGANIZATION_MANAGER => $membership->delegateAreas(),
            default => [],
        };
    }
}
