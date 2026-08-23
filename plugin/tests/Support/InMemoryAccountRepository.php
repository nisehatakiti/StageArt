<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Organization\OrganizationId;

final class InMemoryAccountRepository implements AccountRepositoryInterface
{
    /** @var array<string, Account> */
    private array $accounts = [];

    public function save(Account $account): void
    {
        $this->accounts[$account->id()->toString()] = $account;
    }

    public function findById(AccountId $id): ?Account
    {
        return $this->accounts[$id->toString()] ?? null;
    }

    public function findByIds(array $ids): array
    {
        $result = [];
        foreach ($ids as $id) {
            $account = $this->findById($id);
            if ($account !== null) {
                $result[] = $account;
            }
        }

        return $result;
    }

    public function findByOrganizationId(OrganizationId $organizationId): array
    {
        return array_values(array_filter(
            $this->accounts,
            static fn (Account $account): bool => $account->organizationId()->equals($organizationId)
        ));
    }
}
