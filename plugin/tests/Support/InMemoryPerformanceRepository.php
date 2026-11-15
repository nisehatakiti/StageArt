<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use DateTimeImmutable;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Production\ProductionId;

final class InMemoryPerformanceRepository implements PerformanceRepositoryInterface
{
    /** @var array<string, Performance> */
    private array $performances = [];

    public function save(Performance $performance): void
    {
        $this->performances[$performance->id()->toString()] = $performance;
    }

    public function findById(PerformanceId $id): ?Performance
    {
        return $this->performances[$id->toString()] ?? null;
    }

    /** @var int how often findByIdForUpdate() was called */
    public int $lockCount = 0;

    /** @var callable(PerformanceId): void|null runs when the lock is taken - a test's stand-in for a request that committed just before */
    public $onLock = null;

    public function findByIdForUpdate(PerformanceId $id): ?Performance
    {
        $this->lockCount++;

        if ($this->onLock !== null) {
            ($this->onLock)($id);
        }

        return $this->findById($id);
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_values(array_filter(
            $this->performances,
            static fn (Performance $performance): bool => $performance->productionId()->equals($productionId)
        ));
    }

    public function findByIds(array $ids): array
    {
        $wanted = array_map(static fn (PerformanceId $id): string => $id->toString(), $ids);

        return array_values(array_filter(
            $this->performances,
            static fn (Performance $performance): bool => in_array($performance->id()->toString(), $wanted, true)
        ));
    }

    public function findByProductionAndDateTime(
        ProductionId $productionId,
        DateTimeImmutable $performanceDate,
        string $startTime,
        ?PerformanceId $excludeId = null
    ): ?Performance {
        foreach ($this->performances as $performance) {
            if (! $performance->productionId()->equals($productionId)) {
                continue;
            }

            if ($excludeId !== null && $performance->id()->equals($excludeId)) {
                continue;
            }

            if ($performance->performanceDate()->format('Y-m-d') !== $performanceDate->format('Y-m-d')) {
                continue;
            }

            if ($performance->startTime() !== $startTime) {
                continue;
            }

            return $performance;
        }

        return null;
    }
}
