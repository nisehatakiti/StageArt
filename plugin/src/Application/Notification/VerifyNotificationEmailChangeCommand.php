<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

final class VerifyNotificationEmailChangeCommand
{
    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }
}
