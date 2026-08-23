<?php

declare(strict_types=1);

namespace StageArt\Domain\ProductionDelegate;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Role\RoleKey;

interface ProductionDelegateRepositoryInterface
{
    public function save(ProductionDelegate $delegate): void;

    public function findById(ProductionDelegateId $id): ?ProductionDelegate;

    /**
     * @return ProductionDelegate[]
     */
    public function findByProductionId(ProductionId $productionId): array;

    /**
     * @return ProductionDelegate[]
     */
    public function findByPersonId(PersonId $personId): array;

    public function findByProductionAndPersonAndRole(
        ProductionId $productionId,
        PersonId $personId,
        RoleKey $role
    ): ?ProductionDelegate;

    /**
     * Per ProductionDelegate.md "Remove": deletion is the described
     * operation for removing a delegate (distinct from Participant's
     * "原則として物理削除しない" rule), so this is a real delete, not a
     * Status change.
     */
    public function delete(ProductionDelegateId $id): void;
}
