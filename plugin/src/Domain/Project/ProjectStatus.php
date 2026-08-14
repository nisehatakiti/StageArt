<?php

declare(strict_types=1);

namespace StageArt\Domain\Project;

use InvalidArgumentException;

/**
 * Project.md defines DRAFT/ACTIVE/CLOSED/ARCHIVED but does not mandate a
 * transition order, and Project Lifecycle is explicitly kept independent
 * of Production Lifecycle. This VO only validates membership in the
 * allowed set; it deliberately does not enforce any transition sequence.
 */
final class ProjectStatus
{
    public const DRAFT = 'DRAFT';
    public const ACTIVE = 'ACTIVE';
    public const CLOSED = 'CLOSED';
    public const ARCHIVED = 'ARCHIVED';

    private const VALID = [self::DRAFT, self::ACTIVE, self::CLOSED, self::ARCHIVED];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid ProjectStatus: {$value}");
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
