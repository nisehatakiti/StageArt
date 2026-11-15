<?php

declare(strict_types=1);

namespace StageArt\Application\RehearsalAttendance;

final class RespondRehearsalAttendanceCommand
{
    public string $rehearsalAttendanceId;
    public int $requestedByWordPressUserId;
    public string $status;
    public ?string $remarks;

    public function __construct(string $rehearsalAttendanceId, int $requestedByWordPressUserId, string $status, ?string $remarks = null)
    {
        $this->rehearsalAttendanceId = $rehearsalAttendanceId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->status = $status;
        $this->remarks = $remarks;
    }
}
