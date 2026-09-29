<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Person;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Person\CurrentPersonNotFoundException;
use StageArt\Application\Person\GetPersonByIdQuery;
use StageArt\Application\Person\GetPersonByIdUseCase;
use StageArt\Application\Person\PersonNotFoundException;
use StageArt\Domain\Person\Person;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;

/**
 * StageArt メンバー管理: Person ID検索 (担当者権限をメンバー管理へ統合・整理
 * §1/§2-A instruction) - confirms the newly-added lookup-by-id capability.
 */
final class GetPersonByIdUseCaseTest extends TestCase
{
    private function makeUseCase(InMemoryPersonRepository $people): GetPersonByIdUseCase
    {
        $authorization = new OrganizationAuthorizationService($people, new InMemoryMembershipRepository());

        return new GetPersonByIdUseCase($authorization, $people);
    }

    public function test_returns_the_named_person(): void
    {
        $people = new InMemoryPersonRepository();
        $requester = Person::create(1);
        $people->save($requester);
        $target = Person::create(2);
        $target->setName('山田', '太郎');
        $people->save($target);
        $useCase = $this->makeUseCase($people);

        $result = $useCase->execute(new GetPersonByIdQuery($target->id()->toString(), 1));

        $this->assertSame($target->id()->toString(), $result->id);
        $this->assertSame('山田', $result->familyName);
        $this->assertSame('太郎', $result->givenName);
    }

    public function test_tolerates_a_target_person_with_no_name_set_yet(): void
    {
        $people = new InMemoryPersonRepository();
        $requester = Person::create(1);
        $people->save($requester);
        $target = Person::create(2);
        $people->save($target);
        $useCase = $this->makeUseCase($people);

        $result = $useCase->execute(new GetPersonByIdQuery($target->id()->toString(), 1));

        $this->assertNull($result->familyName);
        $this->assertNull($result->givenName);
    }

    public function test_throws_for_a_nonexistent_person_id(): void
    {
        $people = new InMemoryPersonRepository();
        $requester = Person::create(1);
        $people->save($requester);
        $useCase = $this->makeUseCase($people);

        $this->expectException(PersonNotFoundException::class);

        $useCase->execute(new GetPersonByIdQuery('00000000-0000-0000-0000-000000000000', 1));
    }

    public function test_throws_for_a_malformed_person_id(): void
    {
        $people = new InMemoryPersonRepository();
        $requester = Person::create(1);
        $people->save($requester);
        $useCase = $this->makeUseCase($people);

        $this->expectException(PersonNotFoundException::class);

        $useCase->execute(new GetPersonByIdQuery('not-a-uuid', 1));
    }

    public function test_throws_when_the_requester_has_no_linked_person(): void
    {
        $people = new InMemoryPersonRepository();
        $target = Person::create(2);
        $people->save($target);
        $useCase = $this->makeUseCase($people);

        $this->expectException(CurrentPersonNotFoundException::class);

        $useCase->execute(new GetPersonByIdQuery($target->id()->toString(), 999));
    }
}
