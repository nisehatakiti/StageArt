<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Follow\OrganizationFollow;
use StageArt\Domain\Follow\OrganizationFollowRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

final class InMemoryOrganizationFollowRepository implements OrganizationFollowRepositoryInterface
{
    /** @var array<string, OrganizationFollow> */
    private array $follows = [];

    public function save(OrganizationFollow $follow): void
    {
        $this->follows[$follow->id()->toString()] = $follow;
    }

    public function findByPersonAndOrganization(PersonId $personId, OrganizationId $organizationId): ?OrganizationFollow
    {
        foreach ($this->follows as $follow) {
            if ($follow->personId()->equals($personId) && $follow->organizationId()->equals($organizationId)) {
                return $follow;
            }
        }

        return null;
    }

    public function findActiveByPersonId(PersonId $personId): array
    {
        return array_values(array_filter(
            $this->follows,
            static fn (OrganizationFollow $follow): bool => $follow->personId()->equals($personId) && $follow->isActive()
        ));
    }

    public function countActiveByOrganizationId(OrganizationId $organizationId): int
    {
        return count(array_filter(
            $this->follows,
            static fn (OrganizationFollow $follow): bool => $follow->organizationId()->equals($organizationId) && $follow->isActive()
        ));
    }
}
