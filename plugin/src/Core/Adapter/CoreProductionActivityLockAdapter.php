<?php

declare(strict_types=1);

namespace StageArt\Core\Adapter;

use StageArt\Application\Production\ProductionClosedException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Contract\ProductionActivityLockContract;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Production\ProductionStatus;

/**
 * See ProductionActivityLockContract.
 */
final class CoreProductionActivityLockAdapter implements ProductionActivityLockContract
{
    private const CLOSED = [ProductionStatus::COMPLETED, ProductionStatus::ARCHIVED];

    private ProductionRepositoryInterface $productions;

    public function __construct(ProductionRepositoryInterface $productions)
    {
        $this->productions = $productions;
    }

    public function lockOpenProduction(ProductionId $productionId): void
    {
        $production = $this->productions->findByIdForShare($productionId);

        if ($production === null) {
            throw new ProductionNotFoundException($productionId->toString());
        }

        if (in_array($production->status()->toString(), self::CLOSED, true)) {
            throw new ProductionClosedException($productionId->toString());
        }
    }
}
