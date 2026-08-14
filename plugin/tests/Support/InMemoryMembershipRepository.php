<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Membership\MembershipRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

final class InMemoryMembershipRepository implements MembershipRepositoryInterface
{
    /** @var array<string, Membership> */
    private array $memberships = [];

    public function save(Membership $membership): void
    {
        $this->memberships[$membership->id()->toString()] = $membership;
    }

    public function findByPersonId(PersonId $personId): array
    {
        return array_values(array_filter(
            $this->memberships,
            static fn (Membership $membership): bool => $membership->personId()->equals($personId)
        ));
    }

    public function findByOrganizationAndPerson(OrganizationId $organizationId, PersonId $personId): ?Membership
    {
        foreach ($this->memberships as $membership) {
            if ($membership->organizationId()->equals($organizationId) && $membership->personId()->equals($personId)) {
                return $membership;
            }
        }

        return null;
    }
}
