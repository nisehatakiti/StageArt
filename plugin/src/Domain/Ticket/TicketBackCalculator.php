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
 *
 * Every sold seat carries its own price (PR #7 会計仕様 C): the price
 * frozen on its Reservation at booking (Reservation::priceSnapshot()),
 * never the Ticket's current price, so seats of one Ticket type booked
 * before and after a price change are worth what each was sold for.
 * `calculateForSeatPrices()` takes those per-seat prices in sale order;
 * `calculate()` is the single-price form of it.
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
        return self::calculateForSeatPrices($mode, $conditions, $soldCount > 0 ? array_fill(0, $soldCount, $ticketPrice) : []);
    }

    /**
     * @param TicketBackCondition[] $conditions
     * @param int[] $seatPrices one entry per sold seat, in sale order (the
     *        order decides which seats fall into which 分離方式 band)
     */
    public static function calculateForSeatPrices(TicketBackMode $mode, array $conditions, array $seatPrices): int
    {
        $seatPrices = array_values($seatPrices);

        if ($seatPrices === [] || $conditions === []) {
            return 0;
        }

        return $mode->equals(TicketBackMode::fromString(TicketBackMode::PROGRESSIVE))
            ? self::calculateProgressive($conditions, $seatPrices)
            : self::calculateSeparated($conditions, $seatPrices);
    }

    /**
     * @param TicketBackCondition[] $conditions
     * @param int[] $seatPrices
     */
    private static function calculateProgressive(array $conditions, array $seatPrices): int
    {
        $soldCount = count($seatPrices);
        $sorted = $conditions;
        usort($sorted, static fn (TicketBackCondition $a, TicketBackCondition $b): int => $a->priority() <=> $b->priority());

        foreach ($sorted as $condition) {
            if ($condition->matches($soldCount)) {
                return (int) round(array_sum($seatPrices) * $condition->ratePercent() / 100);
            }
        }

        return 0;
    }

    /**
     * @param TicketBackCondition[] $conditions
     * @param int[] $seatPrices
     */
    private static function calculateSeparated(array $conditions, array $seatPrices): int
    {
        $soldCount = count($seatPrices);
        $sorted = $conditions;
        usort($sorted, static fn (TicketBackCondition $a, TicketBackCondition $b): int => $a->threshold() <=> $b->threshold());

        $total = 0;

        foreach ($sorted as $index => $condition) {
            // Seats are numbered from 1: a band from 0枚 starts at the first
            // seat (counting it from 0 paid one seat that was never sold).
            $lowerBound = max(1, $condition->threshold());
            $nextCondition = $sorted[$index + 1] ?? null;
            $upperBound = $nextCondition !== null ? $nextCondition->threshold() - 1 : $soldCount;

            if ($lowerBound > $soldCount) {
                continue;
            }

            $unitsInBand = min($soldCount, $upperBound) - $lowerBound + 1;

            if ($unitsInBand <= 0) {
                continue;
            }

            $bandSales = array_sum(array_slice($seatPrices, $lowerBound - 1, $unitsInBand));
            $total += (int) round($bandSales * $condition->ratePercent() / 100);
        }

        return $total;
    }
}
