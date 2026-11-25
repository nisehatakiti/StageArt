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

    public ?\Throwable $failNextSave = null;

    public ?LockLog $lockLog = null;

    public function save(ProductionDelegate $delegate): void
    {
        if ($this->failNextSave !== null) {
            $failure = $this->failNextSave;
            $this->failNextSave = null;

            throw $failure;
        }

        $this->delegates[$delegate->id()->toString()] = $delegate;
    }

    public function findByProductionAndPersonForUpdate(ProductionId $productionId, PersonId $personId): array
    {
        if ($this->lockLog !== null) {
            $this->lockLog->record('delegate');
        }

        return array_values(array_filter(
            $this->delegates,
            static fn (ProductionDelegate $delegate): bool => $delegate->productionId()->equals($productionId)
                && $delegate->personId()->equals($personId)
        ));
    }

    /**
     * @return array<string, ProductionDelegate>
     */
    public function snapshot(): array
    {
        return array_map(static fn (ProductionDelegate $delegate): ProductionDelegate => clone $delegate, $this->delegates);
    }

    /**
     * @param array<string, ProductionDelegate> $snapshot
     */
    public function restore(array $snapshot): void
    {
        $this->delegates = $snapshot;
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
