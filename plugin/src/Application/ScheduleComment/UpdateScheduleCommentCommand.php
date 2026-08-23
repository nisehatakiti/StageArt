<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

final class UpdateScheduleCommentCommand
{
    public string $scheduleCommentId;
    public int $requestedByWordPressUserId;
    public string $body;

    public function __construct(string $scheduleCommentId, int $requestedByWordPressUserId, string $body)
    {
        $this->scheduleCommentId = $scheduleCommentId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->body = $body;
    }
}
