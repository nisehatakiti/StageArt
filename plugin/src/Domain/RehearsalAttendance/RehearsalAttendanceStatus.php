<?php

declare(strict_types=1);

namespace StageArt\Domain\RehearsalAttendance;

use InvalidArgumentException;

/**
 * RehearsalAttendance.md "Attendance Status": the two Phases use
 * disjoint-except-UNANSWERED value sets. This VO validates membership in
 * the full 8-value union (matching the DB column's realistic domain);
 * which subset is valid for a given record's Phase is enforced by
 * RehearsalAttendance::class, since only the Entity knows its own Phase.
 */
final class RehearsalAttendanceStatus
{
    public const UNANSWERED = 'UNANSWERED';
    public const AVAILABLE = 'AVAILABLE';
    public const UNAVAILABLE = 'UNAVAILABLE';
    public const ATTENDING = 'ATTENDING';
    public const NOT_ATTENDING = 'NOT_ATTENDING';
    public const ATTENDED = 'ATTENDED';
    public const LATE = 'LATE';
    public const ABSENT = 'ABSENT';

    public const PHASE_1_VALUES = [self::UNANSWERED, self::AVAILABLE, self::UNAVAILABLE];
    public const PHASE_2_VALUES = [
        self::UNANSWERED,
        self::ATTENDING,
        self::NOT_ATTENDING,
        self::ATTENDED,
        self::LATE,
        self::ABSENT,
    ];

    private const VALID = [
        self::UNANSWERED,
        self::AVAILABLE,
        self::UNAVAILABLE,
        self::ATTENDING,
        self::NOT_ATTENDING,
        self::ATTENDED,
        self::LATE,
        self::ABSENT,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid RehearsalAttendanceStatus: {$value}");
        }

        $this->value = $value;
    }

    public static function unanswered(): self
    {
        return new self(self::UNANSWERED);
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
