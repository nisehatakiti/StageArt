<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

final class CreateUserAccountCommand
{
    public int $requestedByWordPressUserId;

    public function __construct(int $requestedByWordPressUserId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
