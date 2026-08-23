<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

final class DeleteProductionDelegateCommand
{
    public string $productionDelegateId;
    public int $requestedByWordPressUserId;

    public function __construct(string $productionDelegateId, int $requestedByWordPressUserId)
    {
        $this->productionDelegateId = $productionDelegateId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
