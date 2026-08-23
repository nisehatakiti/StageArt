<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use RuntimeException;

final class NotificationNotFoundException extends RuntimeException
{
    public function __construct(string $notificationId)
    {
        parent::__construct("Notification not found: {$notificationId}");
    }
}
