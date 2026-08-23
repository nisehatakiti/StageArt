<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\ProductionDelegate\ProductionDelegateId;
use StageArt\Domain\ProductionDelegate\ProductionDelegateRepositoryInterface;
use StageArt\Domain\Role\RoleKey;

final class InMemoryProductionDelegateRepository implements ProductionDelegateRepositoryInterface
{
    /** @var array<string, ProductionDelegate> */
    private array $delegates = [];

    public function save(ProductionDelegate $delegate): void
    {
        $this->delegates[$delegate->id()->toString()] = $delegate;
    }

    public function findById(ProductionDelegateId $id): ?ProductionDelegate
    {
        return $this->delegates[$id->toString()] ?? null;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_values(array_filter(
            $this->delegates,
            static fn (ProductionDelegate $delegate): bool => $delegate->productionId()->equals($productionId)
        ));
    }

    public function findByPersonId(PersonId $personId): array
    {
        return array_values(array_filter(
            $this->delegates,
            static fn (ProductionDelegate $delegate): bool => $delegate->personId()->equals($personId)
        ));
    }

    public function findByProductionAndPersonAndRole(
        ProductionId $productionId,
        PersonId $personId,
        RoleKey $role
    ): ?ProductionDelegate {
        foreach ($this->delegates as $delegate) {
            if (
                $delegate->productionId()->equals($productionId)
                && $delegate->personId()->equals($personId)
                && $delegate->role()->equals($role)
            ) {
                return $delegate;
            }
        }

        return null;
    }

    public function delete(ProductionDelegateId $id): void
    {
        unset($this->delegates[$id->toString()]);
    }
}
