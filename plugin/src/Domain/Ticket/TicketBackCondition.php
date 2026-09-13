<?php

declare(strict_types=1);

namespace StageArt\Domain\Ticket;

use InvalidArgumentException;

/**
 * Chapter 32 §4.2: one row of a Ticket Back rule set - 優先順位/枚数/
 * 条件(以上／以下／未満)/料率. Conditions are evaluated in ascending
 * priority order and the first match wins (累進方式) or, for 分離方式,
 * sorted ascending by threshold to form contiguous count bands (see
 * TicketBackCalculator::class).
 */
final class TicketBackCondition
{
    public const COMPARATOR_GTE = 'GTE'; // 以上
    public const COMPARATOR_LTE = 'LTE'; // 以下
    public const COMPARATOR_LT = 'LT';   // 未満

    private const VALID_COMPARATORS = [
        self::COMPARATOR_GTE,
        self::COMPARATOR_LTE,
        self::COMPARATOR_LT,
    ];

    private int $priority;
    private int $threshold;
    private string $comparator;
    /** Percentage as an integer 0-100 (e.g. 15 for 15%). */
    private int $ratePercent;

    public function __construct(int $priority, int $threshold, string $comparator, int $ratePercent)
    {
        if ($threshold < 0) {
            throw new InvalidArgumentException('Ticket back condition threshold must not be negative.');
        }

        if (! in_array($comparator, self::VALID_COMPARATORS, true)) {
            throw new InvalidArgumentException("Invalid ticket back comparator: {$comparator}");
        }

        if ($ratePercent < 0 || $ratePercent > 100) {
            throw new InvalidArgumentException('Ticket back rate must be between 0 and 100.');
        }

        $this->priority = $priority;
        $this->threshold = $threshold;
        $this->comparator = $comparator;
        $this->ratePercent = $ratePercent;
    }

    public function matches(int $count): bool
    {
        return match ($this->comparator) {
            self::COMPARATOR_GTE => $count >= $this->threshold,
            self::COMPARATOR_LTE => $count <= $this->threshold,
            self::COMPARATOR_LT => $count < $this->threshold,
        };
    }

    public function priority(): int
    {
        return $this->priority;
    }

    public function threshold(): int
    {
        return $this->threshold;
    }

    public function comparator(): string
    {
        return $this->comparator;
    }

    public function ratePercent(): int
    {
        return $this->ratePercent;
    }

    /**
     * @return array{priority:int,threshold:int,comparator:string,rate_percent:int}
     */
    public function toArray(): array
    {
        return [
            'priority' => $this->priority,
            'threshold' => $this->threshold,
            'comparator' => $this->comparator,
            'rate_percent' => $this->ratePercent,
        ];
    }

    /**
     * @param array{priority:int,threshold:int,comparator:string,rate_percent:int} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int) ($data['priority'] ?? 0),
            (int) ($data['threshold'] ?? 0),
            (string) ($data['comparator'] ?? ''),
            (int) ($data['rate_percent'] ?? 0)
        );
    }
}
