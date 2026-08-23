<?php

declare(strict_types=1);

namespace StageArt\Application\UserAccount;

use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Domain\UserAccount\UserAccountRepositoryInterface;

/**
 * Self-service UserAccount creation: a WordPress user obtains a
 * UserAccount (the Authentication Identity, per UserAccount.md) for
 * their own Person. This never acts on another Person's behalf - the
 * target is always resolved from requestedByWordPressUserId, the same
 * pattern CreateOrganizationUseCase uses for the Person lookup/create
 * step, kept outside the transaction for the same reason: a Person
 * existing without a UserAccount is not an invariant violation.
 */
final class CreateUserAccountUseCase
{
    private PersonRepositoryInterface $people;
    private UserAccountRepositoryInterface $userAccounts;
    private TransactionManagerInterface $transactions;

    public function __construct(
        PersonRepositoryInterface $people,
        UserAccountRepositoryInterface $userAccounts,
        TransactionManagerInterface $transactions
    ) {
        $this->people = $people;
        $this->userAccounts = $userAccounts;
        $this->transactions = $transactions;
    }

    public function execute(CreateUserAccountCommand $command): UserAccountResult
    {
        $person = $this->people->findByWordPressUserId($command->requestedByWordPressUserId);

        if (! $person) {
            $person = Person::create($command->requestedByWordPressUserId);
            $this->people->save($person);
        }

        return $this->transactions->run(function () use ($person): UserAccountResult {
            $existing = $this->userAccounts->findByPersonId($person->id());

            if ($existing) {
                throw new UserAccountAlreadyExistsException('A UserAccount already exists for this Person.');
            }

            $userAccount = UserAccount::create($person->id());
            $this->userAccounts->save($userAccount);

            return UserAccountResult::fromDomain($userAccount);
        });
    }
}
