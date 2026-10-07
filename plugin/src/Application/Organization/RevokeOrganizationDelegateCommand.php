<?php

declare(strict_types=1);

namespace StageArt\Application\Organization;

final class RevokeOrganizationDelegateCommand
{
    public string $organizationId;
    public int $requestedByWordPressUserId;
    public string $targetPersonId;

    public function __construct(string $organizationId, int $requestedByWordPressUserId, string $targetPersonId)
    {
        $this->organizationId = $organizationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->targetPersonId = $targetPersonId;
    }
}
