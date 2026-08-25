<?php

declare(strict_types=1);

namespace StageArt\Domain\Membership;

use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

interface MembershipRepositoryInterface
{
    public function save(Membership $membership): void;

    public function findById(MembershipId $id): ?Membership;

    /**
     * @return Membership[]
     */
    public function findByPersonId(PersonId $personId): array;

    public function findByOrganizationAndPerson(OrganizationId $organizationId, PersonId $personId): ?Membership;

    /**
     * @return Membership[]
     */
    public function findByOrganizationId(OrganizationId $organizationId): array;
}
