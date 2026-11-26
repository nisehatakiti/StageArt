<?php

declare(strict_types=1);

namespace StageArt\Application\Settlement;

final class SettleProductionMemberCommand
{
    public string $productionId;
    public string $personId;
    public int $requestedByWordPressUserId;

    public function __construct(string $productionId, string $personId, int $requestedByWordPressUserId)
    {
        $this->productionId = $productionId;
        $this->personId = $personId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
