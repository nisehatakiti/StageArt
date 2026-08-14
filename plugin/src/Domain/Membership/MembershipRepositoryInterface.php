<?php

declare(strict_types=1);

namespace StageArt\Domain\Membership;

use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

interface MembershipRepositoryInterface
{
    public function save(Membership $membership): void;

    /**
     * @return Membership[]
     */
    public function findByPersonId(PersonId $personId): array;

    public function findByOrganizationAndPerson(OrganizationId $organizationId, PersonId $personId): ?Membership;
}
