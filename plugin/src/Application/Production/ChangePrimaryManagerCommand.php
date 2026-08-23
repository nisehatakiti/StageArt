<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

final class ChangePrimaryManagerCommand
{
    public string $productionId;
    public int $requestedByWordPressUserId;
    public string $newPrimaryManagerPersonId;

    public function __construct(string $productionId, int $requestedByWordPressUserId, string $newPrimaryManagerPersonId)
    {
        $this->productionId = $productionId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->newPrimaryManagerPersonId = $newPrimaryManagerPersonId;
    }
}
