<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class CreateOrganizationCommand
{
    public int $requestedByWordPressUserId;
    public string $name;
    public string $slug;
    public ?string $type;
    public ?string $description;

    public function __construct(
        int $requestedByWordPressUserId,
        string $name,
        string $slug,
        ?string $type = null,
        ?string $description = null
    ) {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
        $this->slug = $slug;
        $this->type = $type;
        $this->description = $description;
    }
}
