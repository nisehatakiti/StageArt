<?php

declare(strict_types=1);

namespace StageArt\Domain\Ticket;

/**
 * Chapter 32 §4.5, Phase 3 instruction §47 - pure, stateless, UI-
 * independent quota shortfall calculation:
 *
 * - quota disabled -> no shortfall calculation at all
 * - quota enabled, actual sold >= quota count -> shortfall is 0
 * - quota enabled, actual sold < quota count -> shortfall = quota - sold
 * - buyback OFF -> no payable amount is ever produced from a shortfall
 * - buyback ON -> payable = shortfall * unit price
 */
final class QuotaCalculator
{
    private function __construct()
    {
    }

    public static function shortfall(bool $quotaEnabled, ?int $quotaCount, int $soldCount): int
    {
        if (! $quotaEnabled || $quotaCount === null) {
            return 0;
        }

        return max(0, $quotaCount - $soldCount);
    }

    public static function shortfallPayable(bool $buybackEnabled, ?int $unitPrice, int $shortfall): int
    {
        if (! $buybackEnabled || $unitPrice === null || $shortfall <= 0) {
            return 0;
        }

        return $shortfall * $unitPrice;
    }
}
