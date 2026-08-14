<?php

declare(strict_types=1);

namespace StageArt\Application\Project;

final class CreateProjectCommand
{
    public string $organizationId;
    public int $requestedByWordPressUserId;
    public ?string $name;

    public function __construct(string $organizationId, int $requestedByWordPressUserId, ?string $name = null)
    {
        $this->organizationId = $organizationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
    }
}
