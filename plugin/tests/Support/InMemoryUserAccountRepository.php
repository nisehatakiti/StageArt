<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Domain\UserAccount\UserAccountId;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

final class InMemoryUserAccountRepository implements UserAccountRepositoryInterface
{
    /** @var array<string, UserAccount> */
    private array $userAccounts = [];

    public function save(UserAccount $userAccount): void
    {
        $this->userAccounts[$userAccount->id()->toString()] = $userAccount;
    }

    public function findById(UserAccountId $id): ?UserAccount
    {
        return $this->userAccounts[$id->toString()] ?? null;
    }

    public function findByPersonId(PersonId $personId): ?UserAccount
    {
        foreach ($this->userAccounts as $userAccount) {
            if ($userAccount->personId()->equals($personId)) {
                return $userAccount;
            }
        }

        return null;
    }
}
