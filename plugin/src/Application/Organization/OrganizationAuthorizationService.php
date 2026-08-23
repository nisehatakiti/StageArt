<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

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
}
