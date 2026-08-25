<?php

declare(strict_types=1);

namespace StageArt\Domain\Follow;

use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;

interface OrganizationFollowRepositoryInterface
{
    public function save(OrganizationFollow $follow): void;

    public function findByPersonAndOrganization(PersonId $personId, OrganizationId $organizationId): ?OrganizationFollow;

    /**
     * @return OrganizationFollow[]
     */
    public function findActiveByPersonId(PersonId $personId): array;

    /** docs/03-SocialProfileAndFollowPolicy.md: "Follow数はOrganization側で確認できる". */
    public function countActiveByOrganizationId(OrganizationId $organizationId): int;
}
