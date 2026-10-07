<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class UpdateOrganizationExternalLinkCommand
{
    public string $id;
    public int $requestedByWordPressUserId;
    public string $label;
    public string $url;
    public int $displayOrder;
    public bool $visibility;

    public function __construct(string $id, int $requestedByWordPressUserId, string $label, string $url, int $displayOrder, bool $visibility = true)
    {
        $this->id = $id;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->label = $label;
        $this->url = $url;
        $this->displayOrder = $displayOrder;
        $this->visibility = $visibility;
    }
}
