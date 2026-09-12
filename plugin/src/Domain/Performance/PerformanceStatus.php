<?php

declare(strict_types=1);

namespace StageArt\Domain\Performance;

use InvalidArgumentException;

/**
 * Phase 2 Performance基盤 instruction §7: exactly these five values.
 * SOLD_OUT is kept as a reachable Status even though Phase 2 builds no
 * Ticket/Reservation Domain to compute it automatically ("チケット販売数
 * から自動的にSOLD_OUTへ遷移する処理は実装しません") - a later Ticket Phase is
 * expected to set it, not this one. This VO only validates membership in
 * the allowed set; Performance::class enforces which transitions are
 * reachable from which current Status.
 */
final class PerformanceStatus
{
    public const DRAFT = 'DRAFT';
    public const PUBLISHED = 'PUBLISHED';
    public const SOLD_OUT = 'SOLD_OUT';
    public const FINISHED = 'FINISHED';
    public const CANCELLED = 'CANCELLED';

    private const VALID = [
        self::DRAFT,
        self::PUBLISHED,
        self::SOLD_OUT,
        self::FINISHED,
        self::CANCELLED,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (! in_array($value, self::VALID, true)) {
            throw new InvalidArgumentException("Invalid PerformanceStatus: {$value}");
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
