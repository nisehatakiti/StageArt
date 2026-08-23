<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

final class UpdateProductionDelegateCommand
{
    public string $productionDelegateId;
    public int $requestedByWordPressUserId;
    public string $role;
    public string $status;

    public function __construct(
        string $productionDelegateId,
        int $requestedByWordPressUserId,
        string $role,
        string $status
    ) {
        $this->productionDelegateId = $productionDelegateId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->role = $role;
        $this->status = $status;
    }
}
