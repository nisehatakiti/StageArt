<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Person;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Person\AmbiguousPersonEmailException;
use StageArt\Application\Person\FindPersonByEmailUseCase;
use StageArt\Domain\Notification\NotificationEmail;
use StageArt\Domain\Person\Person;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryNotificationEmailRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class FindPersonByEmailUseCaseTest extends TestCase
{
    private InMemoryEmailCredentialRepository $emailCredentials;
    private InMemoryUserAccountRepository $userAccounts;
    private InMemoryNotificationEmailRepository $notificationEmails;
    private InMemoryPersonRepository $people;
    private FindPersonByEmailUseCase $findPersonByEmail;

    protected function setUp(): void
    {
        $this->emailCredentials = new InMemoryEmailCredentialRepository();
        $this->userAccounts = new InMemoryUserAccountRepository();
        $this->notificationEmails = new InMemoryNotificationEmailRepository();
        $this->people = new InMemoryPersonRepository();

        $this->findPersonByEmail = new FindPersonByEmailUseCase(
            $this->emailCredentials,
            $this->userAccounts,
            $this->notificationEmails,
            $this->people
        );
    }

    public function test_returns_null_when_no_source_has_this_email(): void
    {
        $this->assertNull($this->findPersonByEmail->execute('nobody@example.com'));
    }

    public function test_finds_a_person_via_emailcredential(): void
    {
        $person = Person::create(1);
        $this->people->save($person);
        $userAccount = UserAccount::create($person->id());
        $this->userAccounts->save($userAccount);
        $this->emailCredentials->save(EmailCredential::create($userAccount->id(), 'alice@example.com', 'hash'));

        $result = $this->findPersonByEmail->execute('alice@example.com');

        $this->assertNotNull($result);
        $this->assertSame($person->id()->toString(), $result->id);
    }

    public function test_finds_a_google_only_person_via_a_verified_notificationemail(): void
    {
        $person = Person::create(2);
        $this->people->save($person);
        $this->notificationEmails->save(NotificationEmail::create($person->id(), 'bob@example.com', true, NotificationEmail::SOURCE_GOOGLE));

        $result = $this->findPersonByEmail->execute('bob@example.com');

        $this->assertNotNull($result);
        $this->assertSame($person->id()->toString(), $result->id);
    }

    /** §7's explicit requirement: an unverified NotificationEmail must
     * never be treated as confirming ownership. */
    public function test_does_not_find_a_person_via_an_unverified_notificationemail(): void
    {
        $person = Person::create(3);
        $this->people->save($person);
        $this->notificationEmails->save(NotificationEmail::create($person->id(), 'carol@example.com', false, NotificationEmail::SOURCE_GOOGLE));

        $this->assertNull($this->findPersonByEmail->execute('carol@example.com'));
    }

    public function test_throws_when_emailcredential_and_verified_notificationemail_point_to_different_persons(): void
    {
        $personA = Person::create(4);
        $this->people->save($personA);
        $userAccountA = UserAccount::create($personA->id());
        $this->userAccounts->save($userAccountA);
        $this->emailCredentials->save(EmailCredential::create($userAccountA->id(), 'shared@example.com', 'hash'));

        $personB = Person::create(5);
        $this->people->save($personB);
        $this->notificationEmails->save(NotificationEmail::create($personB->id(), 'shared@example.com', true, NotificationEmail::SOURCE_GOOGLE));

        $this->expectException(AmbiguousPersonEmailException::class);
        $this->findPersonByEmail->execute('shared@example.com');
    }

    public function test_does_not_throw_when_emailcredential_and_notificationemail_agree_on_the_same_person(): void
    {
        $person = Person::create(6);
        $this->people->save($person);
        $userAccount = UserAccount::create($person->id());
        $this->userAccounts->save($userAccount);
        $this->emailCredentials->save(EmailCredential::create($userAccount->id(), 'dave@example.com', 'hash'));
        $this->notificationEmails->save(NotificationEmail::create($person->id(), 'dave@example.com', true, NotificationEmail::SOURCE_GOOGLE));

        $result = $this->findPersonByEmail->execute('dave@example.com');

        $this->assertNotNull($result);
        $this->assertSame($person->id()->toString(), $result->id);
    }
}
