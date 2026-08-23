<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Person;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Person\CurrentPersonNotFoundException;
use StageArt\Application\Person\UpdatePersonNameCommand;
use StageArt\Application\Person\UpdatePersonNameUseCase;
use StageArt\Domain\Person\Person;
use StageArt\Tests\Support\InMemoryPersonRepository;

final class UpdatePersonNameUseCaseTest extends TestCase
{
    public function test_updates_the_callers_own_name(): void
    {
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $useCase = new UpdatePersonNameUseCase($people);

        $result = $useCase->execute(new UpdatePersonNameCommand(1, '秦', '良輔'));

        $this->assertSame('秦', $result->familyName);
        $this->assertSame('良輔', $result->givenName);

        $persisted = $people->findByWordPressUserId(1);
        $this->assertSame('秦', $persisted->familyName());
        $this->assertSame('良輔', $persisted->givenName());
    }

    public function test_trims_surrounding_whitespace(): void
    {
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $useCase = new UpdatePersonNameUseCase($people);

        $result = $useCase->execute(new UpdatePersonNameCommand(1, '  秦  ', '  良輔  '));

        $this->assertSame('秦', $result->familyName);
        $this->assertSame('良輔', $result->givenName);
    }

    public function test_rejects_an_empty_family_name(): void
    {
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $useCase = new UpdatePersonNameUseCase($people);

        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(new UpdatePersonNameCommand(1, '   ', '良輔'));
    }

    public function test_rejects_an_empty_given_name(): void
    {
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $useCase = new UpdatePersonNameUseCase($people);

        $this->expectException(InvalidArgumentException::class);
        $useCase->execute(new UpdatePersonNameCommand(1, '秦', ''));
    }

    public function test_throws_when_no_person_is_linked_to_the_caller(): void
    {
        $useCase = new UpdatePersonNameUseCase(new InMemoryPersonRepository());

        $this->expectException(CurrentPersonNotFoundException::class);
        $useCase->execute(new UpdatePersonNameCommand(999, '秦', '良輔'));
    }

    /**
     * The "本人自身のみ変更可能" requirement: the command carries no Person
     * ID at all, only requestedByWordPressUserId - there is no code path
     * by which caller A could name-update Person B's record, since the
     * target Person is always resolved from the caller's own WordPress
     * User ID, exactly like ChangePasswordUseCase/
     * RequestEmailVerificationUseCase.
     */
    public function test_cannot_affect_a_different_persons_name(): void
    {
        $people = new InMemoryPersonRepository();
        $people->save(Person::create(1));
        $otherPerson = Person::create(2);
        $otherPerson->setName('他人', '太郎');
        $people->save($otherPerson);
        $useCase = new UpdatePersonNameUseCase($people);

        $useCase->execute(new UpdatePersonNameCommand(1, '秦', '良輔'));

        $untouched = $people->findByWordPressUserId(2);
        $this->assertSame('他人', $untouched->familyName());
        $this->assertSame('太郎', $untouched->givenName());
    }
}
