<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

final class GetRehearsalAttendanceQuery
{
    public string $rehearsalAttendanceId;
    public int $requestedByWordPressUserId;

    public function __construct(string $rehearsalAttendanceId, int $requestedByWordPressUserId)
    {
        $this->rehearsalAttendanceId = $rehearsalAttendanceId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
