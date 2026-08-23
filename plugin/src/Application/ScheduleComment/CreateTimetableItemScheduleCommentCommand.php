<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

final class CreateTimetableItemScheduleCommentCommand
{
    public string $timetableItemId;
    public int $requestedByWordPressUserId;
    public string $body;

    public function __construct(string $timetableItemId, int $requestedByWordPressUserId, string $body)
    {
        $this->timetableItemId = $timetableItemId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->body = $body;
    }
}
