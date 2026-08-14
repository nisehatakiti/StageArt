<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationRepositoryInterface;

final class InMemoryOrganizationRepository implements OrganizationRepositoryInterface
{
    /** @var array<string, Organization> */
    private array $organizations = [];

    public function save(Organization $organization): void
    {
        $this->organizations[$organization->id()->toString()] = $organization;
    }

    public function findById(OrganizationId $id): ?Organization
    {
        return $this->organizations[$id->toString()] ?? null;
    }

    public function findByIds(array $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            $organization = $this->findById($id);

            if ($organization !== null) {
                $result[] = $organization;
            }
        }

        return $result;
    }
}
