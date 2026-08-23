<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

final class CreateNewTimetableVersionCommand
{
    public string $rehearsalId;
    public int $requestedByWordPressUserId;

    public function __construct(string $rehearsalId, int $requestedByWordPressUserId)
    {
        $this->rehearsalId = $rehearsalId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
