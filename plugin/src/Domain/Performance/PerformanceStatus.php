<?php

declare(strict_types=1);

namespace StageArt\Domain\Performance;

use InvalidArgumentException;

/**
 * StageArt全体DRAFT廃止 instruction: DRAFT removed. StageArt does not use
 * a DRAFT status to gate public/private visibility - visibility is
 * judged by each feature's own publication condition/date-time instead
 * (docs/04-DomainModel/PublicationStateModel.md). A Performance now
 * starts PUBLISHED (see Performance::create()); SOLD_OUT is kept as a
 * reachable Status even though no Ticket/Reservation Domain computes it
 * automatically yet ("チケット販売数から自動的にSOLD_OUTへ遷移する処理は実装しま
 * せん") - a later Ticket Phase is expected to set it, not this one. This
 * VO only validates membership in the allowed set; Performance::class
 * enforces which transitions are reachable from which current Status.
 */
final class PerformanceStatus
{
    public const PUBLISHED = 'PUBLISHED';
    public const SOLD_OUT = 'SOLD_OUT';
    public const FINISHED = 'FINISHED';
    public const CANCELLED = 'CANCELLED';

    private const VALID = [
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

    public static function published(): self
    {
        return new self(self::PUBLISHED);
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
