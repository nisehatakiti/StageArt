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
    public ?string $slug;
    public ?bool $published;

    /**
     * StageArt Web First Phase 2: `slug`/`published` are optional
     * trailing parameters - `null` means "leave unchanged", distinct
     * from an explicit `false` for `published` (unpublish). Kept
     * optional so every pre-existing caller (Admin UI, REST, tests)
     * continues to work unchanged; only the new slug/publish-aware call
     * sites need to pass them.
     */
    public function __construct(
        string $organizationId,
        int $requestedByWordPressUserId,
        string $name,
        ?string $type,
        ?string $description,
        string $status,
        ?string $slug = null,
        ?bool $published = null
    ) {
        $this->organizationId = $organizationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
        $this->type = $type;
        $this->description = $description;
        $this->status = $status;
        $this->slug = $slug;
        $this->published = $published;
    }
}
