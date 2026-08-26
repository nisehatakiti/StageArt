<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Core\Contract\ProductionSummary;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Production\ProductionId;

/**
 * A hand-written test double for ProductionContextContract - holds
 * plain ProductionSummary values, no `ProductionRepositoryInterface`/
 * Core Infrastructure involved at all. See
 * tests/Application/Rehearsal/RehearsalModuleContractIsolationTest.php.
 */
final class FakeProductionContextContract implements ProductionContextContract
{
    /** @var array<string, ProductionSummary> */
    private array $productions = [];

    /** @var array<string, OrganizationId> */
    private array $organizationIds = [];

    public function register(ProductionId $id, string $name, string $status = 'DRAFT', ?OrganizationId $organizationId = null): void
    {
        $this->productions[$id->toString()] = new ProductionSummary($id, $name, $status);

        if ($organizationId !== null) {
            $this->organizationIds[$id->toString()] = $organizationId;
        }
    }

    public function getProduction(ProductionId $productionId): ?ProductionSummary
    {
        return $this->productions[$productionId->toString()] ?? null;
    }

    public function getProductionOrganizationId(ProductionId $productionId): ?OrganizationId
    {
        return $this->organizationIds[$productionId->toString()] ?? null;
    }
}
