<?php

declare(strict_types=1);

namespace StageArt\Domain\Participant;

use InvalidArgumentException;

/**
 * Participant.md: Subject can be a Person or an Organization. subjectId
 * is stored as a plain UUID string on Participant (rather than a typed
 * PersonId/OrganizationId union, which PHP cannot express without an
 * interface both VOs would need to implement) - callers resolve it back
 * to the correct VO type using this SubjectType.
 */
final class ParticipantSubjectType
{
    public const PERSON = 'PERSON';
    public const ORGANIZATION = 'ORGANIZATION';
    /**
     * StageArt Phase 1 (docs/21-MemberManagementScreen.md §2.1/"Member
     * Registration Scope" - "People without a StageArt account"): a
     * Participant registered by name only, with no linked Person/
     * Organization account yet. `Person` itself requires a WordPress
     * user id (see Person.php's own docblock) - it has no "unclaimed"
     * representation - so a name-only member is modeled at the
     * Participant level instead, carrying its own `displayName` and a
     * self-referential placeholder `subjectId` (never looked up as a
     * real Person/Organization). A future It's ME claim flow (Blueprint
     * §11, not built this Phase) would transition such a Participant to
     * PERSON once claimed - not implemented here.
     */
    public const NAME_ONLY = 'NAME_ONLY';

    private const VALID = [self::PERSON, self::ORGANIZATION, self::NAME_ONLY];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid ParticipantSubjectType: {$value}");
        }

        $this->value = $value;
    }

    public static function person(): self
    {
        return new self(self::PERSON);
    }

    public static function organization(): self
    {
        return new self(self::ORGANIZATION);
    }

    public static function nameOnly(): self
    {
        return new self(self::NAME_ONLY);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
