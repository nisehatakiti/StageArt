<?php

declare(strict_types=1);

namespace StageArt\Domain\Person;

/**
 * StageArt Authentication Phase 6: family_name/given_name were added as
 * Person's own basic identifying information (not a new Profile Domain
 * concept - Profile.md's Biography/Image/Website/SNS remain
 * unimplemented and untouched by this Phase). Both are nullable: a
 * freshly-created Person (Email or Google) has neither set until the
 * user completes the set-name.tsx screen - this is the normal, expected
 * state right after registration, not an error condition (see
 * GetCurrentPersonUseCase's docblock for how mobile-rn's post-login gate
 * uses this).
 */
final class Person
{
    private PersonId $id;
    private int $wordPressUserId;
    private ?string $familyName;
    private ?string $givenName;

    private function __construct(PersonId $id, int $wordPressUserId, ?string $familyName, ?string $givenName)
    {
        $this->id = $id;
        $this->wordPressUserId = $wordPressUserId;
        $this->familyName = $familyName;
        $this->givenName = $givenName;
    }

    public static function create(int $wordPressUserId): self
    {
        return new self(PersonId::generate(), $wordPressUserId, null, null);
    }

    public static function reconstitute(
        PersonId $id,
        int $wordPressUserId,
        ?string $familyName = null,
        ?string $givenName = null
    ): self {
        return new self($id, $wordPressUserId, $familyName, $givenName);
    }

    public function id(): PersonId
    {
        return $this->id;
    }

    public function wordPressUserId(): int
    {
        return $this->wordPressUserId;
    }

    public function familyName(): ?string
    {
        return $this->familyName;
    }

    public function givenName(): ?string
    {
        return $this->givenName;
    }

    public function hasName(): bool
    {
        return $this->familyName !== null && $this->givenName !== null;
    }

    public function setName(string $familyName, string $givenName): void
    {
        $this->familyName = $familyName;
        $this->givenName = $givenName;
    }
}
