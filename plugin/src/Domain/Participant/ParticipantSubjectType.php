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

    private const VALID = [self::PERSON, self::ORGANIZATION];

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
