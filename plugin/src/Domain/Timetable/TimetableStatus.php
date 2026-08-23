<?php

declare(strict_types=1);

namespace StageArt\Domain\Timetable;

use InvalidArgumentException;

/**
 * Timetable.md "# Status": a closed, Blueprint-confirmed 3-value set
 * (unlike Timetable Item's "Category", which Timetable.md itself
 * presents as non-exhaustive examples - see TimetableItem's plain
 * string $category field).
 */
final class TimetableStatus
{
    public const DRAFT = 'DRAFT';
    public const PUBLISHED = 'PUBLISHED';
    public const ARCHIVED = 'ARCHIVED';

    private const VALID = [self::DRAFT, self::PUBLISHED, self::ARCHIVED];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid TimetableStatus: {$value}");
        }

        $this->value = $value;
    }

    public static function draft(): self
    {
        return new self(self::DRAFT);
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
