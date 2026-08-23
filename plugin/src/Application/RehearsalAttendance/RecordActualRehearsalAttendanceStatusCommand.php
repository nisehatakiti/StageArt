<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

final class RecordActualRehearsalAttendanceStatusCommand
{
    public string $rehearsalAttendanceId;
    public int $requestedByWordPressUserId;
    public string $status;

    public function __construct(string $rehearsalAttendanceId, int $requestedByWordPressUserId, string $status)
    {
        $this->rehearsalAttendanceId = $rehearsalAttendanceId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->status = $status;
    }
}
