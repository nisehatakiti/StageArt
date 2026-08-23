<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

final class RegisterEmailCredentialCommand
{
    public int $requestedByWordPressUserId;
    public string $email;
    public string $password;

    public function __construct(int $requestedByWordPressUserId, string $email, string $password)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->email = $email;
        $this->password = $password;
    }
}
