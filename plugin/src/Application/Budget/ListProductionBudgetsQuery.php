<?php

declare(strict_types=1);

namespace StageArt\Application\Budget;

final class ListProductionBudgetsQuery
{
    public string $productionId;
    public int $requestedByWordPressUserId;

    public function __construct(string $productionId, int $requestedByWordPressUserId)
    {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
