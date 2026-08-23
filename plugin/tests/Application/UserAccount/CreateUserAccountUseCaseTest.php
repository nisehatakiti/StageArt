<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\UserAccount;

use PHPUnit\Framework\TestCase;
use StageArt\Application\UserAccount\CreateUserAccountCommand;
use StageArt\Application\UserAccount\CreateUserAccountUseCase;
use StageArt\Application\UserAccount\UserAccountAlreadyExistsException;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\InMemoryUserAccountRepository;

final class CreateUserAccountUseCaseTest extends TestCase
{
    public function test_create_assigns_active_status_and_creates_a_person_when_none_exists(): void
    {
        $people = new InMemoryPersonRepository();
        $userAccounts = new InMemoryUserAccountRepository();

        $useCase = new CreateUserAccountUseCase($people, $userAccounts, new InMemoryTransactionManager());

        $result = $useCase->execute(new CreateUserAccountCommand(42));

        $this->assertSame('ACTIVE', $result->status);

        $person = $people->findByWordPressUserId(42);
        $this->assertNotNull($person);
        $this->assertSame($person->id()->toString(), $result->personId);

        $userAccount = $userAccounts->findByPersonId($person->id());
        $this->assertNotNull($userAccount);
        $this->assertTrue($userAccount->isActive());
    }

    public function test_reuses_the_existing_person_for_the_same_wordpress_user(): void
    {
        $people = new InMemoryPersonRepository();
        $userAccounts = new InMemoryUserAccountRepository();

        $useCase = new CreateUserAccountUseCase($people, $userAccounts, new InMemoryTransactionManager());
        $useCase->execute(new CreateUserAccountCommand(7));

        $firstPerson = $people->findByWordPressUserId(7);
        $this->assertNotNull($firstPerson);

        // A second UserAccount creation attempt for the same WordPress user
        // must be rejected (see next test), but the Person lookup itself
        // must not create a duplicate Person along the way.
        try {
            $useCase->execute(new CreateUserAccountCommand(7));
        } catch (UserAccountAlreadyExistsException $exception) {
            // Expected; assertion below still applies.
        }

        $secondPerson = $people->findByWordPressUserId(7);
        $this->assertTrue($firstPerson->id()->equals($secondPerson->id()));
    }

    public function test_creating_a_second_user_account_for_the_same_person_is_rejected(): void
    {
        $people = new InMemoryPersonRepository();
        $userAccounts = new InMemoryUserAccountRepository();

        $useCase = new CreateUserAccountUseCase($people, $userAccounts, new InMemoryTransactionManager());
        $useCase->execute(new CreateUserAccountCommand(1));

        $this->expectException(UserAccountAlreadyExistsException::class);

        $useCase->execute(new CreateUserAccountCommand(1));
    }
}
