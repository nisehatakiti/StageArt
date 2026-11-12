<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Organization\OrganizationSNS;
use StageArt\Domain\Organization\OrganizationSNSId;
use StageArt\Domain\Organization\OrganizationSNSRepositoryInterface;

final class InMemoryOrganizationSNSRepository implements OrganizationSNSRepositoryInterface
{
    /** @var array<string, OrganizationSNS> */
    private array $records = [];

    public function save(OrganizationSNS $sns): void
    {
        $this->records[$sns->id()->toString()] = $sns;
    }

    public function findById(OrganizationSNSId $id): ?OrganizationSNS
    {
        return $this->records[$id->toString()] ?? null;
    }

    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        $matches = array_values(array_filter(
            $this->records,
            static fn (OrganizationSNS $sns): bool => $sns->organizationId()->equals($organizationId)
        ));

        usort($matches, static fn (OrganizationSNS $a, OrganizationSNS $b): int => $a->displayOrder() <=> $b->displayOrder());

        return $matches;
    }

    public function delete(OrganizationSNSId $id): void
    {
        unset($this->records[$id->toString()]);
    }
}
