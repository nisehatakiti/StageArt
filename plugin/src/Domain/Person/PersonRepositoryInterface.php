<?php

declare(strict_types=1);

namespace StageArt\Domain\Person;

interface PersonRepositoryInterface
{
    public function save(Person $person): void;

    public function findById(PersonId $id): ?Person;

    public function findByWordPressUserId(int $wordPressUserId): ?Person;
}
