<?php

declare(strict_types=1);

namespace StageArt\Application\Dashboard;

final class GetMyDashboardQuery
{
    public int $requestedByWordPressUserId;

    public function __construct(int $requestedByWordPressUserId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
