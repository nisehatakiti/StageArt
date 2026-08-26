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
    public ?string $publishedAt;

    /**
     * StageArt Web First Phase 2 / Publication State Model:
     * `slug`/`published`/`publishedAt` are optional trailing parameters -
     * `null` means "leave unchanged" (or, for `published`, "no change to
     * the boolean publish/unpublish action"). Kept optional so every
     * pre-existing caller (Admin UI, REST, tests) continues to work
     * unchanged. `publishedAt` (an ISO 8601 datetime string) is only
     * consulted when `published === true`: if given, it schedules
     * publication for that moment (may be in the future - see
     * Organization::publish()); if omitted, publication takes effect
     * immediately (`published: true` alone), matching every pre-existing
     * call site's behavior exactly.
     */
    public function __construct(
        string $organizationId,
        int $requestedByWordPressUserId,
        string $name,
        ?string $type,
        ?string $description,
        string $status,
        ?string $slug = null,
        ?bool $published = null,
        ?string $publishedAt = null
    ) {
        $this->organizationId = $organizationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->name = $name;
        $this->type = $type;
        $this->description = $description;
        $this->status = $status;
        $this->slug = $slug;
        $this->published = $published;
        $this->publishedAt = $publishedAt;
    }
}
