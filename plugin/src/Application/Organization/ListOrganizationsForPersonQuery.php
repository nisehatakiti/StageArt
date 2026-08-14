<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class ListOrganizationsForPersonQuery
{
    public int $requestedByWordPressUserId;

    public function __construct(int $requestedByWordPressUserId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
