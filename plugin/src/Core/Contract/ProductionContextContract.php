<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Production\ProductionId;

/**
 * StageArt Core/Module Architecture: read-only access to "does this
 * Production exist, and what is its current basic state" - the
 * `ProductionContextProvider` concept `03-ModularArchitecture.md` §6
 * already named. A Domain Module resolves a Production it needs
 * business context for through here, not through
 * `ProductionRepositoryInterface`/the `Production` Domain Entity
 * directly.
 */
interface ProductionContextContract
{
    public function getProduction(ProductionId $productionId): ?ProductionSummary;
}
