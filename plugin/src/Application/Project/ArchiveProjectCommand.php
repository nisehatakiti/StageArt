<?php

declare(strict_types=1);

namespace StageArt\Application\Project;

final class ArchiveProjectCommand
{
    public string $projectId;
    public int $requestedByWordPressUserId;

    public function __construct(string $projectId, int $requestedByWordPressUserId)
    {
        $this->projectId = $projectId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
