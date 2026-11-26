<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

/**
 * StageArt Admin Console V1: one row of the Account Management table.
 * `name` falls back to the WordPress display name when Person's own
 * family_name/given_name are still unset (a freshly-registered account
 * that has not completed set-name yet - see Person.php's own docblock),
 * so the list never shows a blank name column.
 */
final class AdminAccountResult
{
    public string $userAccountId;
    public string $personId;
    public string $name;
    public string $email;
    public string $status;
    public bool $hasEmailCredential;

    public function __construct(
        string $userAccountId,
        string $personId,
        string $name,
        string $email,
        string $status,
        bool $hasEmailCredential
    ) {
        $this->userAccountId = $userAccountId;
        $this->personId = $personId;
        $this->name = $name;
        $this->email = $email;
        $this->status = $status;
        $this->hasEmailCredential = $hasEmailCredential;
    }
}
