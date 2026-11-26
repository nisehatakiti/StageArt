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
     * R14-B §4.6 / §5.4: every row (any role, any status) of one Person in
     * one Production, locked (SELECT ... FOR UPDATE) - the last lock in the
     * order Production -> invitation -> Participant -> delegate. Used to
     * deactivate them together with the Participant's state change. Throws
     * when the read fails.
     *
     * @return ProductionDelegate[]
     */
    public function findByProductionAndPersonForUpdate(ProductionId $productionId, PersonId $personId): array;

    /**
     * Per ProductionDelegate.md "Remove": deletion is the described
     * operation for removing a delegate (distinct from Participant's
     * "原則として物理削除しない" rule), so this is a real delete, not a
     * Status change.
     */
    public function delete(ProductionDelegateId $id): void;
}
