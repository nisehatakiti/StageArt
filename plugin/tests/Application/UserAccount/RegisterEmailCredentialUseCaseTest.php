<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\UserAccount;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\UserAccount\EmailAlreadyInUseException;
use StageArt\Application\UserAccount\EmailCredentialAlreadyExistsException;
use StageArt\Application\UserAccount\RegisterEmailCredentialCommand;
use StageArt\Application\UserAccount\RegisterEmailCredentialUseCase;
use StageArt\Application\UserAccount\UserAccountNotFoundException;
use StageArt\Domain\Person\Person;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class RegisterEmailCredentialUseCaseTest extends TestCase
{
    /**
     * @return array{0: InMemoryPersonRepository, 1: InMemoryUserAccountRepository, 2: InMemoryEmailCredentialRepository}
     */
    private function makeContextWithUserAccount(int $wordPressUserId): array
    {
        $people = new InMemoryPersonRepository();
        $userAccounts = new InMemoryUserAccountRepository();
        $emailCredentials = new InMemoryEmailCredentialRepository();

        $person = Person::create($wordPressUserId);
        $people->save($person);
        $userAccounts->save(UserAccount::create($person->id()));

        return [$people, $userAccounts, $emailCredentials];
    }

    public function test_register_succeeds_and_hash_is_not_the_plaintext_password(): void
    {
        [$people, $userAccounts, $emailCredentials] = $this->makeContextWithUserAccount(1);

        $useCase = new RegisterEmailCredentialUseCase(
            $people,
            $userAccounts,
            $emailCredentials,
            new InMemoryTransactionManager()
        );

        $result = $useCase->execute(new RegisterEmailCredentialCommand(1, 'owner@example.com', 'correct horse battery'));

        $this->assertSame('owner@example.com', $result->email);
        $this->assertFalse($result->emailVerified);

        $person = $people->findByWordPressUserId(1);
        $userAccount = $userAccounts->findByPersonId($person->id());
        $credential = $emailCredentials->findByUserAccountId($userAccount->id());

        $this->assertNotNull($credential);
        $this->assertNotSame('correct horse battery', $credential->passwordHash());
        $this->assertTrue(password_verify('correct horse battery', $credential->passwordHash()));
    }

    public function test_registration_fails_when_no_user_account_exists(): void
    {
        $people = new InMemoryPersonRepository();
        $userAccounts = new InMemoryUserAccountRepository();
        $emailCredentials = new InMemoryEmailCredentialRepository();

        $useCase = new RegisterEmailCredentialUseCase(
            $people,
            $userAccounts,
            $emailCredentials,
            new InMemoryTransactionManager()
        );

        $this->expectException(UserAccountNotFoundException::class);

        $useCase->execute(new RegisterEmailCredentialCommand(1, 'owner@example.com', 'correct horse battery'));
    }

    public function test_registering_a_second_credential_for_the_same_user_account_is_rejected(): void
    {
        [$people, $userAccounts, $emailCredentials] = $this->makeContextWithUserAccount(1);

        $useCase = new RegisterEmailCredentialUseCase(
            $people,
            $userAccounts,
            $emailCredentials,
            new InMemoryTransactionManager()
        );

        $useCase->execute(new RegisterEmailCredentialCommand(1, 'first@example.com', 'correct horse battery'));

        $this->expectException(EmailCredentialAlreadyExistsException::class);

        $useCase->execute(new RegisterEmailCredentialCommand(1, 'second@example.com', 'correct horse battery'));
    }

    public function test_registering_an_email_already_used_by_another_account_is_rejected(): void
    {
        $people = new InMemoryPersonRepository();
        $userAccounts = new InMemoryUserAccountRepository();
        $emailCredentials = new InMemoryEmailCredentialRepository();

        $personA = Person::create(1);
        $people->save($personA);
        $userAccounts->save(UserAccount::create($personA->id()));

        $personB = Person::create(2);
        $people->save($personB);
        $userAccounts->save(UserAccount::create($personB->id()));

        $useCase = new RegisterEmailCredentialUseCase(
            $people,
            $userAccounts,
            $emailCredentials,
            new InMemoryTransactionManager()
        );

        $useCase->execute(new RegisterEmailCredentialCommand(1, 'shared@example.com', 'correct horse battery'));

        $this->expectException(EmailAlreadyInUseException::class);

        $useCase->execute(new RegisterEmailCredentialCommand(2, 'shared@example.com', 'another password'));
    }

    public function test_password_shorter_than_the_minimum_length_is_rejected(): void
    {
        [$people, $userAccounts, $emailCredentials] = $this->makeContextWithUserAccount(1);

        $useCase = new RegisterEmailCredentialUseCase(
            $people,
            $userAccounts,
            $emailCredentials,
            new InMemoryTransactionManager()
        );

        $this->expectException(InvalidArgumentException::class);

        $useCase->execute(new RegisterEmailCredentialCommand(1, 'owner@example.com', 'short'));
    }
}
