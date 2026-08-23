<?php

declare(strict_types=1);

namespace StageArt\Application\Account;

final class ListAccountsForOrganizationQuery
{
    public string $organizationId;
    public int $requestedByWordPressUserId;

    public function __construct(string $organizationId, int $requestedByWordPressUserId)
    {
        $this->organizationId = $organizationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
