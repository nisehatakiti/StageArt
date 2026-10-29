<?php

declare(strict_types=1);

namespace StageArt\Application\Accounting;

use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Organization\OrganizationId;

/**
 * 修正8: the credit side of an approved Expense is 未払金 (LIABILITY) -
 * ReservationConsistencyPolicy.md "Staff Settlement and Netting" counts
 * 承認済み立替経費 among the Organization's 未払金, and
 * AccountConsistencyPolicy.md "Initial Account Set" lists 未払金 as a
 * standard Account. Nobody picks it by hand (Expense.md: users never enter
 * debit / credit).
 *
 * Reuses the Organization's ACTIVE LIABILITY Account named 未払金; when
 * there is none, creates it the way CheckIn\StandardAccountResolver
 * creates 現金 / チケット売上 (Account::create, name + type, no code). An
 * INACTIVE 未払金 is not reused, because an inactive Account takes no new
 * entries (Account.md "Inactive").
 */
final class StandardPayableAccountResolver
{
    private const PAYABLE_ACCOUNT_NAME = '未払金';

    private AccountRepositoryInterface $accounts;

    public function __construct(AccountRepositoryInterface $accounts)
    {
        $this->accounts = $accounts;
    }

    public function resolvePayableAccount(OrganizationId $organizationId): Account
    {
        foreach ($this->accounts->findByOrganizationId($organizationId) as $account) {
            if ($account->name() === self::PAYABLE_ACCOUNT_NAME
                && $account->type()->toString() === AccountType::LIABILITY
                && $account->isActive()
            ) {
                return $account;
            }
        }

        $account = Account::create($organizationId, self::PAYABLE_ACCOUNT_NAME, AccountType::fromString(AccountType::LIABILITY));
        $this->accounts->save($account);

        return $account;
    }
}
