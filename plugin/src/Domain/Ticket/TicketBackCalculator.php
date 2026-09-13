<?php

declare(strict_types=1);

namespace StageArt\Domain\Ticket;

/**
 * Chapter 32 §4.3/§4.4 - pure, stateless calculation, no UI dependency
 * (Phase 3 instruction §46: "Ticket Back計算ロジックはUIに書かない...
 * Domain/Application側でテスト可能な形にする").
 *
 * 累進方式 (PROGRESSIVE): conditions are evaluated in ascending priority
 * order; the FIRST matching condition's rate is applied to the entire
 * sold quantity and its sales amount ("15枚 × チケット価格 × 15%").
 *
 * 分離方式 (SEPARATED): conditions are treated as ascending count-band
 * lower bounds (sorted by `threshold`), each band's own rate applies
 * only to the units that fall within that band, and the per-band amounts
 * are summed ("tickets 1-10 use rate A, 11-15 use rate B, ... all
 * accumulated"). Only GTE-style thresholds form a coherent contiguous
 * banding; conditions are sorted by threshold ascending regardless of
 * their own comparator for this mode, matching the worked example in
 * §27 (1-10 / 11-20 / 21+).
 */
final class TicketBackCalculator
{
    private function __construct()
    {
    }

    /**
     * @param TicketBackCondition[] $conditions
     */
    public static function calculate(TicketBackMode $mode, array $conditions, int $soldCount, int $ticketPrice): int
    {
        if ($soldCount <= 0 || $conditions === []) {
            return 0;
        }

        return $mode->equals(TicketBackMode::fromString(TicketBackMode::PROGRESSIVE))
            ? self::calculateProgressive($conditions, $soldCount, $ticketPrice)
            : self::calculateSeparated($conditions, $soldCount, $ticketPrice);
    }

    /**
     * @param TicketBackCondition[] $conditions
     */
    private static function calculateProgressive(array $conditions, int $soldCount, int $ticketPrice): int
    {
        $sorted = $conditions;
        usort($sorted, static fn (TicketBackCondition $a, TicketBackCondition $b): int => $a->priority() <=> $b->priority());

        foreach ($sorted as $condition) {
            if ($condition->matches($soldCount)) {
                return (int) round($soldCount * $ticketPrice * $condition->ratePercent() / 100);
            }
        }

        return 0;
    }

    /**
     * @param TicketBackCondition[] $conditions
     */
    private static function calculateSeparated(array $conditions, int $soldCount, int $ticketPrice): int
    {
        $sorted = $conditions;
        usort($sorted, static fn (TicketBackCondition $a, TicketBackCondition $b): int => $a->threshold() <=> $b->threshold());

        $total = 0;

        foreach ($sorted as $index => $condition) {
            $lowerBound = $condition->threshold();
            $nextCondition = $sorted[$index + 1] ?? null;
            $upperBound = $nextCondition !== null ? $nextCondition->threshold() - 1 : $soldCount;

            if ($lowerBound > $soldCount) {
                continue;
            }

            $unitsInBand = min($soldCount, $upperBound) - $lowerBound + 1;

            if ($unitsInBand <= 0) {
                continue;
            }

            $total += (int) round($unitsInBand * $ticketPrice * $condition->ratePercent() / 100);
        }

        return $total;
    }
}
