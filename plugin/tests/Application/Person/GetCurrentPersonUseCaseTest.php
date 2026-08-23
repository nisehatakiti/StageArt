<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Person;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Person\CurrentPersonNotFoundException;
use StageArt\Application\Person\GetCurrentPersonUseCase;
use StageArt\Domain\Person\Person;
use StageArt\Domain\UserAccount\EmailCredential;
use StageArt\Domain\UserAccount\UserAccount;
use StageArt\Tests\Support\InMemoryEmailCredentialRepository;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class GetCurrentPersonUseCaseTest extends TestCase
{
    private function makeUseCase(
        InMemoryPersonRepository $people,
        InMemoryUserAccountRepository $userAccounts,
        InMemoryEmailCredentialRepository $emailCredentials
    ): GetCurrentPersonUseCase {
        $authorization = new OrganizationAuthorizationService($people, new InMemoryMembershipRepository());

        return new GetCurrentPersonUseCase($authorization, $userAccounts, $emailCredentials);
    }

    public function test_returns_the_persons_own_id(): void
    {
        $people = new InMemoryPersonRepository();
        $person = Person::create(42);
        $people->save($person);
        $useCase = $this->makeUseCase($people, new InMemoryUserAccountRepository(), new InMemoryEmailCredentialRepository());

        $result = $useCase->execute(42);

        $this->assertSame($person->id()->toString(), $result->id);
        $this->assertSame(42, $result->wordPressUserId);
    }

    public function test_throws_when_no_person_is_linked(): void
    {
        $useCase = $this->makeUseCase(
            new InMemoryPersonRepository(),
            new InMemoryUserAccountRepository(),
            new InMemoryEmailCredentialRepository()
        );

        $this->expectException(CurrentPersonNotFoundException::class);

        $useCase->execute(999);
    }

    public function test_email_verified_is_true_when_no_useraccount_exists(): void
    {
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $useCase = $this->makeUseCase($people, new InMemoryUserAccountRepository(), new InMemoryEmailCredentialRepository());

        $result = $useCase->execute(1);

        $this->assertTrue($result->emailVerified);
    }

    public function test_email_verified_is_true_for_a_google_only_useraccount_with_no_emailcredential(): void
    {
        $people = new InMemoryPersonRepository();
        $person = Person::create(2);
        $people->save($person);
        $userAccounts = new InMemoryUserAccountRepository();
        $userAccounts->save(UserAccount::create($person->id()));
        $useCase = $this->makeUseCase($people, $userAccounts, new InMemoryEmailCredentialRepository());

        $result = $useCase->execute(2);

        $this->assertTrue($result->emailVerified);
    }

    public function test_email_verified_is_false_for_an_unverified_emailcredential(): void
    {
        $people = new InMemoryPersonRepository();
        $person = Person::create(3);
        $people->save($person);
        $userAccounts = new InMemoryUserAccountRepository();
        $userAccount = UserAccount::create($person->id());
        $userAccounts->save($userAccount);
        $emailCredentials = new InMemoryEmailCredentialRepository();
        $emailCredentials->save(EmailCredential::create($userAccount->id(), 'unverified@example.com', 'hash'));
        $useCase = $this->makeUseCase($people, $userAccounts, $emailCredentials);

        $result = $useCase->execute(3);

        $this->assertFalse($result->emailVerified);
    }

    public function test_email_verified_is_true_for_a_verified_emailcredential(): void
    {
        $people = new InMemoryPersonRepository();
        $person = Person::create(4);
        $people->save($person);
        $userAccounts = new InMemoryUserAccountRepository();
        $userAccount = UserAccount::create($person->id());
        $userAccounts->save($userAccount);
        $emailCredentials = new InMemoryEmailCredentialRepository();
        $credential = EmailCredential::create($userAccount->id(), 'verified@example.com', 'hash');
        $credential->markEmailVerified();
        $emailCredentials->save($credential);
        $useCase = $this->makeUseCase($people, $userAccounts, $emailCredentials);

        $result = $useCase->execute(4);

        $this->assertTrue($result->emailVerified);
    }

    /**
     * StageArt Authentication Phase 6: a freshly-created Person (Email or
     * Google) has no name set yet - this is the normal state right after
     * registration, before the user completes set-name.tsx, not an
     * error.
     */
    public function test_family_name_and_given_name_are_null_by_default(): void
    {
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(5));
        $useCase = $this->makeUseCase($people, new InMemoryUserAccountRepository(), new InMemoryEmailCredentialRepository());

        $result = $useCase->execute(5);

        $this->assertNull($result->familyName);
        $this->assertNull($result->givenName);
    }

    public function test_family_name_and_given_name_reflect_a_person_who_has_set_their_name(): void
    {
        $people = new InMemoryPersonRepository();
        $person = Person::create(6);
        $person->setName('秦', '良輔');
        $people->save($person);
        $useCase = $this->makeUseCase($people, new InMemoryUserAccountRepository(), new InMemoryEmailCredentialRepository());

        $result = $useCase->execute(6);

        $this->assertSame('秦', $result->familyName);
        $this->assertSame('良輔', $result->givenName);
    }
}
