<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Core\Contract\ProductionCapabilityDirectoryContract;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

final class FakeProductionCapabilityDirectoryContract implements ProductionCapabilityDirectoryContract
{
    /** @var array<string, PersonId[]> */
    private array $holders = [];

    public function register(ProductionId $productionId, string $capability, PersonId ...$personIds): void
    {
        $this->holders["{$productionId->toString()}|{$capability}"] = $personIds;
    }

    public function personIdsWithProductionCapability(ProductionId $productionId, string $capability): array
    {
        return $this->holders["{$productionId->toString()}|{$capability}"] ?? [];
    }
}
