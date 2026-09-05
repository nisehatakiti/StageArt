<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\UserAccount\UserAccountId;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * StageArt Admin Console V1: the Account Management screen's "削除" bulk
 * action. This is a logical delete only - calls UserAccount.disable()
 * (docs/04-DomainModel/UserAccount.md's DISABLED status: "恒久的に利用
 * 停止した状態...Personおよび過去のBusiness Dataは削除しない"), the
 * same precedent DeleteOrganizationUseCase already set for Organization
 * ("原則として物理削除しない"). No row is ever physically DELETEd, and
 * Person/Membership/Participant/etc. Business Data tied to this account
 * is completely untouched.
 */
final class DeleteUserAccountsUseCase
{
    private UserAccountRepositoryInterface $userAccounts;
    private TransactionManagerInterface $transactions;

    public function __construct(UserAccountRepositoryInterface $userAccounts, TransactionManagerInterface $transactions)
    {
        $this->userAccounts = $userAccounts;
        $this->transactions = $transactions;
    }

    public function execute(DeleteUserAccountsCommand $command): void
    {
        $this->transactions->run(function () use ($command): void {
            foreach ($command->userAccountIds as $rawId) {
                $userAccount = $this->userAccounts->findById(UserAccountId::fromString($rawId));

                if (! $userAccount) {
                    continue;
                }

                $userAccount->disable();
                $this->userAccounts->save($userAccount);
            }
        });
    }
}
