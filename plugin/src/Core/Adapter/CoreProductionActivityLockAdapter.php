<?php

declare(strict_types=1);

namespace StageArt\Core\Adapter;

use StageArt\Application\Production\ProductionClosedException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\ProductionActivityLockContract;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

/**
 * See ProductionActivityLockContract.
 */
final class CoreProductionActivityLockAdapter implements ProductionActivityLockContract
{
    private ProductionRepositoryInterface $productions;

    public function __construct(ProductionRepositoryInterface $productions)
    {
        $this->productions = $productions;
    }

    public function lockOpenProduction(ProductionId $productionId): void
    {
        ProductionClosedException::throwIfIn($this->lockShared($productionId), ProductionClosedException::CLOSED_FOR_ACTIVITY);
    }

    public function lockUnsettledProduction(ProductionId $productionId): void
    {
        ProductionClosedException::throwIfIn($this->lockShared($productionId), ProductionClosedException::SETTLED);
    }

    public function lockUnsettledProductionExclusively(ProductionId $productionId): void
    {
        ProductionClosedException::throwIfIn($this->lockExclusive($productionId), ProductionClosedException::SETTLED);
    }

    public function lockProductionExclusively(ProductionId $productionId): void
    {
        $this->lockExclusive($productionId);
    }

    private function lockExclusive(ProductionId $productionId): Production
    {
        $production = $this->productions->findByIdForUpdate($productionId);

        if ($production === null) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        return $production;
    }

    private function lockShared(ProductionId $productionId): Production
    {
        $production = $this->productions->findByIdForShare($productionId);

        if ($production === null) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        return $production;
    }
}
