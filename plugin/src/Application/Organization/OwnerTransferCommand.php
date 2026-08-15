<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class OwnerTransferCommand
{
    public string $organizationId;
    public int $requestedByWordPressUserId;
    public string $newOwnerPersonId;

    public function __construct(string $organizationId, int $requestedByWordPressUserId, string $newOwnerPersonId)
    {
        $this->organizationId = $organizationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->newOwnerPersonId = $newOwnerPersonId;
    }
}
