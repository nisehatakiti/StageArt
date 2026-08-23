<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use InvalidArgumentException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\EmailCredentialRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * Registers the EmailCredential for the requesting WordPress user's own
 * UserAccount. Requires a UserAccount to already exist (EmailCredential
 * belongs to a UserAccount, per UserAccount.md's "UserAccount ->
 * EmailCredential (0..1)" structure) - this Use Case does not create one
 * implicitly, matching CreateOrganizationUseCase's and
 * OwnerTransferUseCase's refusal to provision a UserAccount as a side
 * effect of an unrelated operation.
 *
 * Password hashing algorithm choice: UserAccount.md defers the concrete
 * algorithm to "Authentication Infrastructure実装時" - this slice uses
 * PHP's built-in password_hash() with PASSWORD_DEFAULT (currently
 * bcrypt), since it requires no additional dependency and composer.json
 * intentionally has none beyond PHPUnit. This is an implementation
 * decision, not a Blueprint-mandated one; revisit if Authentication
 * Infrastructure design settles on something else.
 */
final class RegisterEmailCredentialUseCase
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

    public function execute(RegisterEmailCredentialCommand $command): EmailCredentialResult
    {
        $person = $this->people->findByWordPressUserId($command->requestedByWordPressUserId);

        if (! $person) {
            throw new UserAccountNotFoundException(
                'No UserAccount exists for this WordPress user. Create a UserAccount first.'
            );
        }

        $userAccount = $this->userAccounts->findByPersonId($person->id());

        if (! $userAccount) {
            throw new UserAccountNotFoundException(
                'No UserAccount exists for this WordPress user. Create a UserAccount first.'
            );
        }

        if (strlen($command->password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException(
                'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.'
            );
        }

        return $this->transactions->run(function () use ($userAccount, $command): EmailCredentialResult {
            if ($this->emailCredentials->findByUserAccountId($userAccount->id())) {
                throw new EmailCredentialAlreadyExistsException(
                    'This UserAccount already has an EmailCredential.'
                );
            }

            $existingByEmail = $this->emailCredentials->findByEmail($command->email);

            if ($existingByEmail && ! $existingByEmail->userAccountId()->equals($userAccount->id())) {
                throw new EmailAlreadyInUseException('This email address is already registered to another account.');
            }

            $passwordHash = password_hash($command->password, PASSWORD_DEFAULT);

            $credential = EmailCredential::create($userAccount->id(), $command->email, $passwordHash);
            $this->emailCredentials->save($credential);

            return EmailCredentialResult::fromDomain($credential);
        });
    }
}
