<?php

declare(strict_types=1);

namespace StageArt\Application\Project;

final class ListProjectsForPersonQuery
{
    public int $requestedByWordPressUserId;

    public function __construct(int $requestedByWordPressUserId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
