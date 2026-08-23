<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionAccounting;

final class GetProductionAccountingSummaryQuery
{
    public string $productionId;
    public int $requestedByWordPressUserId;

    public function __construct(string $productionId, int $requestedByWordPressUserId)
    {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
