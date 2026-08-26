<?php

declare(strict_types=1);

namespace StageArt\Core\Adapter;

use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Core\Contract\ProductionSummary;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;

final class CoreProductionContextAdapter implements ProductionContextContract
{
    private ProductionRepositoryInterface $productions;

    public function __construct(ProductionRepositoryInterface $productions)
    {
        $this->productions = $productions;
    }

    public function getProduction(ProductionId $productionId): ?ProductionSummary
    {
        $production = $this->productions->findById($productionId);

        if ($production === null) {
            return null;
        }

        return new ProductionSummary(
            $production->id(),
            $production->name()->toString(),
            $production->status()->toString()
        );
    }
}
