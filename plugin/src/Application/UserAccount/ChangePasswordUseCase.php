<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use InvalidArgumentException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * Always acts on the caller's own UserAccount (resolved from
 * requestedByWordPressUserId, never a request parameter), matching every
 * other Use Case in this Controller. Requires the current password to be
 * confirmed first, whichever provider the caller's active session came
 * from (Google or Email+Password) - this is the one place a Google-only
 * user cannot reach without first registering an EmailCredential (see
 * EmailCredentialNotFoundException).
 */
final class ChangePasswordUseCase
{
    private const MIN_PASSWORD_LENGTH = 8;

    private PersonRepositoryInterface $people;
    private UserAccountRepositoryInterface $userAccounts;
    private EmailCredentialRepositoryInterface $emailCredentials;
    private TransactionManagerInterface $transactions;

    public function __construct(
        PersonRepositoryInterface $people,
        UserAccountRepositoryInterface $userAccounts,
        EmailCredentialRepositoryInterface $emailCredentials,
        TransactionManagerInterface $transactions
    ) {
        $this->people = $people;
        $this->userAccounts = $userAccounts;
        $this->emailCredentials = $emailCredentials;
        $this->transactions = $transactions;
    }

    public function execute(ChangePasswordCommand $command): void
    {
        if (strlen($command->newPassword) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(
                'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'
            );
        }

        $person = $this->people->findByWordPressUserId($command->requestedByWordPressUserId);

        if (! $person) {
            throw new UserAccountNotFoundException('No UserAccount exists for this WordPress user.');
        }

        $userAccount = $this->userAccounts->findByPersonId($person->id());

        if (! $userAccount) {
            throw new UserAccountNotFoundException('No UserAccount exists for this WordPress user.');
        }

        $this->transactions->run(function () use ($userAccount, $command): void {
            $credential = $this->emailCredentials->findByUserAccountId($userAccount->id());

            if (! $credential) {
                throw new EmailCredentialNotFoundException('No EmailCredential exists for this UserAccount.');
            }

            if (! password_verify($command->currentPassword, $credential->passwordHash())) {
                throw new CurrentPasswordIncorrectException('The current password is incorrect.');
            }

            $credential->changePasswordHash(password_hash($command->newPassword, PASSWORD_DEFAULT));
            $this->emailCredentials->save($credential);
        });
    }
}
