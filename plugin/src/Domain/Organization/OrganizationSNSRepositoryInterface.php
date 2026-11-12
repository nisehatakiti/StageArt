<?php

declare(strict_types=1);

namespace StageArt\Domain\Organization;

interface OrganizationSNSRepositoryInterface
{
    public function save(OrganizationSNS $sns): void;

    public function findById(OrganizationSNSId $id): ?OrganizationSNS;

    /**
     * @return OrganizationSNS[] ordered by display_order ascending
     */
    public function findByOrganizationId(OrganizationId $organizationId): array;

    public function delete(OrganizationSNSId $id): void;
}
