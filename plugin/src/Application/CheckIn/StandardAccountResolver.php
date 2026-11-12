<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use StageArt\Domain\Account\Account;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\Account\AccountType;
use StageArt\Domain\Organization\OrganizationId;

/**
 * TicketRevenueConsistencyPolicy.md/CheckIn.md: "具体的な勘定科目...は
 * Accounting Domainで定義する" - Check-in itself must not invent a new
 * accounting model, and this Phase must reuse the existing chart of
 * accounts, not design a new one. `CreateOrganizationUseCase` already
 * establishes a "現金" ASSET Account as this codebase's one existing
 * cash-handling convention, but only creates it when Accounting is
 * enabled AT Organization-creation time with an opening balance entered
 * (see that class's own `createStandardAccounts()`) - an Organization
 * that turns Accounting on later, or was created without an opening
 * balance, has no "現金" Account yet, and no Account for チケット売上
 * (Ticket Revenue) exists in this codebase's standard chart at all.
 *
 * This resolver find-or-creates exactly those two Accounts by name/type
 * within the Organization, additively extending the existing "現金"
 * convention rather than replacing it - disclosed as a judgment call
 * since Blueprint leaves "具体的な勘定科目" unspecified for Check-in.
 */
final class StandardAccountResolver
{
    private const CASH_ACCOUNT_NAME = '現金';
    private const TICKET_REVENUE_ACCOUNT_NAME = 'チケット売上';

    private AccountRepositoryInterface $accounts;

    public function __construct(AccountRepositoryInterface $accounts)
    {
        $this->accounts = $accounts;
    }

    public function resolveCashAccount(OrganizationId $organizationId): Account
    {
        return $this->findOrCreate($organizationId, self::CASH_ACCOUNT_NAME, AccountType::ASSET);
    }

    public function resolveTicketRevenueAccount(OrganizationId $organizationId): Account
    {
        return $this->findOrCreate($organizationId, self::TICKET_REVENUE_ACCOUNT_NAME, AccountType::REVENUE);
    }

    private function findOrCreate(OrganizationId $organizationId, string $name, string $type): Account
    {
        foreach ($this->accounts->findByOrganizationId($organizationId) as $account) {
            if ($account->name() === $name && $account->type()->toString() === $type) {
                return $account;
            }
        }

        $account = Account::create($organizationId, $name, AccountType::fromString($type));
        $this->accounts->save($account);

        return $account;
    }
}
