<?php

declare(strict_types=1);

namespace StageArt\Domain\Person;

/**
 * Deliberately minimal: only the WordPress User linkage from
 * docs/04-DomainModel/Person.md is implemented. Profile-level fields are
 * out of scope for the Organization vertical slice.
 */
final class Person
{
    private PersonId $id;
    private int $wordPressUserId;

    private function __construct(PersonId $id, int $wordPressUserId)
    {
        $this->id = $id;
        $this->wordPressUserId = $wordPressUserId;
    }

    public static function create(int $wordPressUserId): self
    {
        return new self(PersonId::generate(), $wordPressUserId);
    }

    public static function reconstitute(PersonId $id, int $wordPressUserId): self
    {
        return new self($id, $wordPressUserId);
    }

    public function id(): PersonId
    {
        return $this->id;
    }

    public function wordPressUserId(): int
    {
        return $this->wordPressUserId;
    }
}
