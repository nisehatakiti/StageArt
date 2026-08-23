<?php

declare(strict_types=1);

namespace StageArt\Application\Timetable;

final class PublishTimetableVersionCommand
{
    public string $timetableId;
    public int $requestedByWordPressUserId;
    public ?string $changeSummary;

    public function __construct(string $timetableId, int $requestedByWordPressUserId, ?string $changeSummary)
    {
        $this->timetableId = $timetableId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->changeSummary = $changeSummary;
    }
}
