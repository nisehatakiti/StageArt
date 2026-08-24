<?php

declare(strict_types=1);

namespace StageArt\Application\Production;

final class CreateProductionCommand
{
    public int $requestedByWordPressUserId;
    public string $projectId;
    public string $name;
    public string $slug;
    public string $primaryManagerPersonId;
    public ?string $titleHeading;

    public function __construct(
        int $requestedByWordPressUserId,
        string $projectId,
        string $name,
        string $slug,
        string $primaryManagerPersonId,
        ?string $titleHeading = null
    ) {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->projectId = $projectId;
        $this->name = $name;
        $this->slug = $slug;
        $this->primaryManagerPersonId = $primaryManagerPersonId;
        $this->titleHeading = $titleHeading;
    }
}
