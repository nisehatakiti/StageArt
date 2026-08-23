<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\UserAccount;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\UserAccount\ChangePasswordCommand;
use StageArt\Application\UserAccount\ChangePasswordUseCase;
use StageArt\Application\UserAccount\CurrentPasswordIncorrectException;
use StageArt\Application\UserAccount\EmailCredentialNotFoundException;
use StageArt\Application\UserAccount\RegisterEmailCredentialCommand;
use StageArt\Application\UserAccount\RegisterEmailCredentialUseCase;
use StageArt\Application\UserAccount\UserAccountNotFoundException;
use StageArt\Domain\Person\Person;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class ChangePasswordUseCaseTest extends TestCase
{
    private InMemoryPersonRepository $people;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryEmailCredentialRepository $emailCredentials;
    private ChangePasswordUseCase $changePassword;
    private RegisterEmailCredentialUseCase $registerEmailCredential;

    protected function setUp(): void
    {
        $this->people = new InMemoryPersonRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $this->changePassword = new ChangePasswordUseCase(
            $this->people,
            $this->userAccounts,
            $this->emailCredentials,
            new InMemoryTransactionManager()
        );
        $this->registerEmailCredential = new RegisterEmailCredentialUseCase(
            $this->people,
            $this->userAccounts,
            $this->emailCredentials,
            new InMemoryTransactionManager()
        );
    }

    private function createAccountWithPassword(int $wordPressUserId, string $password): void
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->userAccounts->save(UserAccount::create($person->id()));
        $this->registerEmailCredential->execute(
            new RegisterEmailCredentialCommand($wordPressUserId, "user{$wordPressUserId}@example.com", $password)
        );
    }

    public function test_changing_password_with_correct_current_password_succeeds(): void
    {
        $this->createAccountWithPassword(1, 'oldpassword1');

        $this->changePassword->execute(new ChangePasswordCommand(1, 'oldpassword1', 'newpassword2'));

        $person = $this->people->findByWordPressUserId(1);
        $userAccount = $this->userAccounts->findByPersonId($person->id());
        $credential = $this->emailCredentials->findByUserAccountId($userAccount->id());

        $this->assertTrue(password_verify('newpassword2', $credential->passwordHash()));
        $this->assertFalse(password_verify('oldpassword1', $credential->passwordHash()));
    }

    public function test_changing_password_with_incorrect_current_password_is_rejected(): void
    {
        $this->createAccountWithPassword(2, 'oldpassword1');

        $this->expectException(CurrentPasswordIncorrectException::class);
        $this->changePassword->execute(new ChangePasswordCommand(2, 'wrong-password', 'newpassword2'));
    }

    public function test_changing_password_with_a_too_short_new_password_is_rejected(): void
    {
        $this->createAccountWithPassword(3, 'oldpassword1');

        $this->expectException(InvalidArgumentException::class);
        $this->changePassword->execute(new ChangePasswordCommand(3, 'oldpassword1', 'short'));
    }

    public function test_changing_password_without_an_existing_useraccount_is_rejected(): void
    {
        $this->expectException(UserAccountNotFoundException::class);
        $this->changePassword->execute(new ChangePasswordCommand(999, 'anything', 'newpassword2'));
    }

    public function test_changing_password_without_an_existing_emailcredential_is_rejected(): void
    {
        $person = Person::create(4);
        $this->people->save($person);
        $this->userAccounts->save(UserAccount::create($person->id()));

        $this->expectException(EmailCredentialNotFoundException::class);
        $this->changePassword->execute(new ChangePasswordCommand(4, 'anything', 'newpassword2'));
    }
}
