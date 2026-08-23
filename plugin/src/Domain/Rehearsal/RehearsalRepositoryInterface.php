<?php

declare(strict_types=1);

namespace StageArt\Domain\Rehearsal;

use StageArt\Domain\Production\ProductionId;

interface RehearsalRepositoryInterface
{
    public function save(Rehearsal $rehearsal): void;

    public function findById(RehearsalId $id): ?Rehearsal;

    /**
     * @return Rehearsal[]
     */
    public function findByProductionId(ProductionId $productionId): array;

    /**
     * @param RehearsalId[] $ids
     * @return Rehearsal[]
     */
    public function findByIds(array $ids): array;
}
