<?php

declare(strict_types=1);

namespace StageArt\Application\Performance;

final class GetPerformanceQuery
{
    public string $performanceId;
    public int $requestedByWordPressUserId;

    public function __construct(string $performanceId, int $requestedByWordPressUserId)
    {
        $this->performanceId = $performanceId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
