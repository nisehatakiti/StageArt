<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;

final class InMemoryPersonRepository implements PersonRepositoryInterface
{
    /** @var array<string, Person> */
    private array $people = [];

    public function save(Person $person): void
    {
        $this->people[$person->id()->toString()] = $person;
    }

    public function findById(PersonId $id): ?Person
    {
        return $this->people[$id->toString()] ?? null;
    }

    public function findByWordPressUserId(int $wordPressUserId): ?Person
    {
        foreach ($this->people as $person) {
            if ($person->wordPressUserId() === $wordPressUserId) {
                return $person;
            }
        }

        return null;
    }
}
