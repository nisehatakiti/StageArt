<?php

declare(strict_types=1);

namespace StageArt\Domain\Performance;

use StageArt\Domain\Production\ProductionId;

interface PerformanceRepositoryInterface
{
    public function save(Performance $performance): void;

    public function findById(PerformanceId $id): ?Performance;

    /**
     * @return Performance[]
     */
    public function findByProductionId(ProductionId $productionId): array;

    /**
     * @param PerformanceId[] $ids
     * @return Performance[]
     */
    public function findByIds(array $ids): array;
}
