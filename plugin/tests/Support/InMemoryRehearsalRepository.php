<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;

final class InMemoryRehearsalRepository implements RehearsalRepositoryInterface
{
    /** @var array<string, Rehearsal> */
    private array $rehearsals = [];

    public function save(Rehearsal $rehearsal): void
    {
        $this->rehearsals[$rehearsal->id()->toString()] = $rehearsal;
    }

    public function findById(RehearsalId $id): ?Rehearsal
    {
        return $this->rehearsals[$id->toString()] ?? null;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_values(array_filter(
            $this->rehearsals,
            static fn (Rehearsal $rehearsal): bool => $rehearsal->productionId()->equals($productionId)
        ));
    }

    public function findByIds(array $ids): array
    {
        $wanted = array_map(static fn (RehearsalId $id): string => $id->toString(), $ids);

        return array_values(array_filter(
            $this->rehearsals,
            static fn (Rehearsal $rehearsal): bool => in_array($rehearsal->id()->toString(), $wanted, true)
        ));
    }
}
