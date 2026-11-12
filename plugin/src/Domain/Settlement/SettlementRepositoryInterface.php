<?php

declare(strict_types=1);

namespace StageArt\Domain\Settlement;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

interface SettlementRepositoryInterface
{
    public function save(ProductionMemberSettlement $settlement): void;

    public function findByProductionAndPerson(ProductionId $productionId, PersonId $personId): ?ProductionMemberSettlement;

    /**
     * @return ProductionMemberSettlement[]
     */
    public function findByProductionId(ProductionId $productionId): array;
}
