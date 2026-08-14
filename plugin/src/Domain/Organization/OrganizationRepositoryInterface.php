<?php

declare(strict_types=1);

namespace StageArt\Domain\Organization;

interface OrganizationRepositoryInterface
{
    public function save(Organization $organization): void;

    public function findById(OrganizationId $id): ?Organization;

    /**
     * @param OrganizationId[] $ids
     * @return Organization[]
     */
    public function findByIds(array $ids): array;
}
