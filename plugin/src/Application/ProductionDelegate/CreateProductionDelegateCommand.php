<?php

declare(strict_types=1);

namespace StageArt\Application\ProductionDelegate;

final class CreateProductionDelegateCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public string $personId;
    public string $role;

    public function __construct(string $productionId, int $requestedByWordPressUserId, string $personId, string $role)
    {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->personId = $personId;
        $this->role = $role;
    }
}
