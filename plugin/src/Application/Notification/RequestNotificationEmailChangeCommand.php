<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

final class RequestNotificationEmailChangeCommand
{
    public int $requestedByWordPressUserId;
    public string $email;

    public function __construct(int $requestedByWordPressUserId, string $email)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->email = $email;
    }
}
