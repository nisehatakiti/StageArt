<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class UpdateOrganizationCommand
{
    public string $organizationId;
    public int $requestedByWordPressUserId;
    public string $name;
    public ?string $type;
    public ?string $description;
    public string $status;

    public function __construct(
        string $organizationId,
        int $requestedByWordPressUserId,
        string $name,
        ?string $type,
        ?string $description,
        string $status
    ) {
        $this->organizationId = $organizationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
        $this->type = $type;
        $this->description = $description;
        $this->status = $status;
    }
}
