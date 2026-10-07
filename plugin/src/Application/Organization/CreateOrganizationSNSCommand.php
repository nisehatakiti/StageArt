<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class CreateOrganizationSNSCommand
{
    public string $organizationId;
    public int $requestedByWordPressUserId;
    public string $service;
    public string $accountIdentifier;
    public string $profileUrl;
    public ?string $displayName;
    public int $displayOrder;
    public bool $visibility;

    public function __construct(
        string $organizationId,
        int $requestedByWordPressUserId,
        string $service,
        string $accountIdentifier,
        string $profileUrl,
        ?string $displayName = null,
        int $displayOrder = 0,
        bool $visibility = true
    ) {
        $this->organizationId = $organizationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->service = $service;
        $this->accountIdentifier = $accountIdentifier;
        $this->profileUrl = $profileUrl;
        $this->displayName = $displayName;
        $this->displayOrder = $displayOrder;
        $this->visibility = $visibility;
    }
}
