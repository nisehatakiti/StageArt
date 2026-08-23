<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

final class MarkNotificationReadCommand
{
    public string $notificationId;
    public int $requestedByWordPressUserId;

    public function __construct(string $notificationId, int $requestedByWordPressUserId)
    {
        $this->notificationId = $notificationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
