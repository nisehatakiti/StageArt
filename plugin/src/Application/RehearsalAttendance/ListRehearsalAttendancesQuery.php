<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

final class ListRehearsalAttendancesQuery
{
    public string $rehearsalId;
    public string $phase;
    public int $requestedByWordPressUserId;

    public function __construct(string $rehearsalId, string $phase, int $requestedByWordPressUserId)
    {
        $this->rehearsalId = $rehearsalId;
        $this->phase = $phase;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
