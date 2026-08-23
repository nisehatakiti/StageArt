<?php

declare(strict_types=1);

namespace StageArt\Application\ScheduleComment;

use RuntimeException;

final class ScheduleCommentNotFoundException extends RuntimeException
{
    public function __construct(string $scheduleCommentId)
    {
        parent::__construct("ScheduleComment not found: {$scheduleCommentId}");
    }
}
