<?php

declare(strict_types=1);

namespace StageArt\Application\Account;

use RuntimeException;

final class AccountNotFoundException extends RuntimeException
{
    public function __construct(string $accountId)
    {
        parent::__construct("Account not found: {$accountId}");
    }
}
