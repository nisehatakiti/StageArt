<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

final class DeleteScheduleCommentCommand
{
    public string $scheduleCommentId;
    public int $requestedByWordPressUserId;

    public function __construct(string $scheduleCommentId, int $requestedByWordPressUserId)
    {
        $this->scheduleCommentId = $scheduleCommentId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
