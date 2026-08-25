<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

final class ListPendingParticipantRequestsQuery
{
    public int $requestedByWordPressUserId;
    public string $productionId;

    public function __construct(int $requestedByWordPressUserId, string $productionId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->productionId = $productionId;
    }
}
