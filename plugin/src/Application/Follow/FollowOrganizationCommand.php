<?php

declare(strict_types=1);

namespace StageArt\Application\Follow;

final class FollowOrganizationCommand
{
    public int $requestedByWordPressUserId;
    public string $organizationId;

    public function __construct(int $requestedByWordPressUserId, string $organizationId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->organizationId = $organizationId;
    }
}
