<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Settlement\ProductionMemberSettlement;
use StageArt\Domain\Settlement\SettlementRepositoryInterface;

final class InMemorySettlementRepository implements SettlementRepositoryInterface
{
    /** @var array<string, ProductionMemberSettlement> */
    private array $settlements = [];

    public function save(ProductionMemberSettlement $settlement): void
    {
        $this->settlements[$settlement->id()->toString()] = $settlement;
    }

    public function findByProductionAndPerson(ProductionId $productionId, PersonId $personId): ?ProductionMemberSettlement
    {
        foreach ($this->settlements as $settlement) {
            if ($settlement->productionId()->equals($productionId) && $settlement->personId()->equals($personId)) {
                return $settlement;
            }
        }

        return null;
    }

    public function findByProductionId(ProductionId $productionId): array
    {
        return array_values(array_filter(
            $this->settlements,
            static fn (ProductionMemberSettlement $settlement): bool => $settlement->productionId()->equals($productionId)
        ));
    }
}
