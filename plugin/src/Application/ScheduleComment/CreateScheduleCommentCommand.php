<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

final class CreateScheduleCommentCommand
{
    public string $rehearsalId;
    public int $requestedByWordPressUserId;
    public string $body;

    public function __construct(string $rehearsalId, int $requestedByWordPressUserId, string $body)
    {
        $this->rehearsalId = $rehearsalId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->body = $body;
    }
}
