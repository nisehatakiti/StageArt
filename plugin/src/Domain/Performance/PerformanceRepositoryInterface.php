<?php

declare(strict_types=1);

namespace StageArt\Domain\Performance;

use DateTimeImmutable;
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

    /**
     * docs/12-FunctionalStructure.md §22.5: the same-Production, same
     * (performance date + start time) duplicate lookup. `$excludeId` lets
     * an update check for a duplicate against every *other* Performance
     * without excluding itself first (a naive "does any match exist"
     * query would otherwise reject a no-op update that leaves the date/
     * time unchanged).
     */
    public function findByProductionAndDateTime(
        ProductionId $productionId,
        DateTimeImmutable $performanceDate,
        string $startTime,
        ?PerformanceId $excludeId = null
    ): ?Performance;
}
