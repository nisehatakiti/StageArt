<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

final class ListMyNotificationsQuery
{
    public int $requestedByWordPressUserId;

    public function __construct(int $requestedByWordPressUserId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
