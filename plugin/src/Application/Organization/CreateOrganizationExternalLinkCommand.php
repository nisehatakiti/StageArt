<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class CreateOrganizationExternalLinkCommand
{
    public string $organizationId;
    public int $requestedByWordPressUserId;
    public string $label;
    public string $url;
    public int $displayOrder;

    public function __construct(
        string $organizationId,
        int $requestedByWordPressUserId,
        string $label,
        string $url,
        int $displayOrder = 0
    ) {
        $this->organizationId = $organizationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->label = $label;
        $this->url = $url;
        $this->displayOrder = $displayOrder;
    }
}
