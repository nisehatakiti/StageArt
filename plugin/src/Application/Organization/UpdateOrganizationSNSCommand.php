<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class UpdateOrganizationSNSCommand
{
    public string $id;
    public int $requestedByWordPressUserId;
    public string $service;
    public string $accountIdentifier;
    public string $profileUrl;
    public ?string $displayName;
    public int $displayOrder;
    public bool $visibility;

    public function __construct(
        string $id,
        int $requestedByWordPressUserId,
        string $service,
        string $accountIdentifier,
        string $profileUrl,
        ?string $displayName,
        int $displayOrder,
        bool $visibility
    ) {
        $this->id = $id;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->service = $service;
        $this->accountIdentifier = $accountIdentifier;
        $this->profileUrl = $profileUrl;
        $this->displayName = $displayName;
        $this->displayOrder = $displayOrder;
        $this->visibility = $visibility;
    }
}
