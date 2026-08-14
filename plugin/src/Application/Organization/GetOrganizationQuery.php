<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class GetOrganizationQuery
{
    public string $organizationId;
    public int $requestedByWordPressUserId;

    public function __construct(string $organizationId, int $requestedByWordPressUserId)
    {
        $this->organizationId = $organizationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
