<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

final class ListPublicPerformancesQuery
{
    public string $productionId;

    public function __construct(string $productionId)
    {
        $this->productionId = $productionId;
    }
}
