<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

final class ChangePasswordCommand
{
    public int $requestedByWordPressUserId;
    public string $currentPassword;
    public string $newPassword;

    public function __construct(int $requestedByWordPressUserId, string $currentPassword, string $newPassword)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->currentPassword = $currentPassword;
        $this->newPassword = $newPassword;
    }
}
