<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class DeleteOrganizationExternalLinkCommand
{
    public string $id;
    public int $requestedByWordPressUserId;

    public function __construct(string $id, int $requestedByWordPressUserId)
    {
        $this->id = $id;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
