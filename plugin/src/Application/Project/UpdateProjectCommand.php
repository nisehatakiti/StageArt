<?php

declare(strict_types=1);

namespace StageArt\Application\Project;

final class UpdateProjectCommand
{
    public string $projectId;
    public int $requestedByWordPressUserId;
    public ?string $name;
    public string $status;

    public function __construct(string $projectId, int $requestedByWordPressUserId, ?string $name, string $status)
    {
        $this->projectId = $projectId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
        $this->status = $status;
    }
}
