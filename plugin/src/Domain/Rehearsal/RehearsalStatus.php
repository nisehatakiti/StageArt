<?php

declare(strict_types=1);

namespace StageArt\Domain\Rehearsal;

use InvalidArgumentException;

/**
 * Rehearsal.md's "Status Lifecycle" section defines the allowed
 * transition graph explicitly (unlike ProjectStatus, which deliberately
 * does not mandate a sequence): DRAFT -> SCHEDULED -> CONFIRMED ->
 * ACTIVE -> COMPLETED, with CANCELLED reachable from any of the first
 * four. COMPLETED and CANCELLED are terminal ("CANCELLEDおよび
 * COMPLETEDから、通常のRehearsal Lifecycleへ戻すことはしない"). This VO
 * only validates membership in the allowed set; Rehearsal::class enforces
 * the transition graph itself, since only the Entity has both the
 * current and target Status in hand.
 */
final class RehearsalStatus
{
    public const DRAFT = 'DRAFT';
    public const SCHEDULED = 'SCHEDULED';
    public const CONFIRMED = 'CONFIRMED';
    public const ACTIVE = 'ACTIVE';
    public const COMPLETED = 'COMPLETED';
    public const CANCELLED = 'CANCELLED';

    private const VALID = [
        self::DRAFT,
        self::SCHEDULED,
        self::CONFIRMED,
        self::ACTIVE,
        self::COMPLETED,
        self::CANCELLED,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid RehearsalStatus: {$value}");
        }

        $this->value = $value;
    }

    public static function scheduled(): self
    {
        return new self(self::SCHEDULED);
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
