<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Organization\OrganizationId;
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

    /**
     * Resolves the Organization a Production belongs to (Production ->
     * Project -> Organization internally - Project is a Core-internal
     * bridge no Module needs to know exists, per
     * `Application\Production\ProductionOrganizationResolver`'s own
     * docblock). Only the Accounting Module currently calls this (to
     * validate an Account reference belongs to the same Organization as
     * the Production it's budgeted against) - most Modules never need
     * to call it at all.
     */
    public function getProductionOrganizationId(ProductionId $productionId): ?OrganizationId;
}
