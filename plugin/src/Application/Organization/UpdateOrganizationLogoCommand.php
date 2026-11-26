<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class UpdateOrganizationLogoCommand
{
    public string $organizationId;
    public int $requestedByWordPressUserId;
    public ?string $logoUrl;

    public function __construct(string $organizationId, int $requestedByWordPressUserId, ?string $logoUrl)
    {
        $this->organizationId = $organizationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->logoUrl = $logoUrl;
    }
}
