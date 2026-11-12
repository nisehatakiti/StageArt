<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\UserAccount\UserAccountId;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * StageArt Admin Console V1: the Account Management screen's "ブロック"
 * bulk action. Calls UserAccount.suspend() - a Domain method that
 * already existed (docs/04-DomainModel/UserAccount.md's SUSPENDED
 * status) but had no caller anywhere before this Phase. Unknown IDs are
 * silently skipped rather than failing the whole batch, so one stale
 * checkbox selection (e.g. a row deleted by a concurrent admin) does not
 * block the rest.
 */
final class BlockUserAccountsUseCase
{
    private UserAccountRepositoryInterface $userAccounts;
    private TransactionManagerInterface $transactions;

    public function __construct(UserAccountRepositoryInterface $userAccounts, TransactionManagerInterface $transactions)
    {
        $this->userAccounts = $userAccounts;
        $this->transactions = $transactions;
    }

    public function execute(BlockUserAccountsCommand $command): void
    {
        $this->transactions->run(function () use ($command): void {
            foreach ($command->userAccountIds as $rawId) {
                $userAccount = $this->userAccounts->findById(UserAccountId::fromString($rawId));

                if (! $userAccount) {
                    continue;
                }

                $userAccount->suspend();
                $this->userAccounts->save($userAccount);
            }
        });
    }
}
