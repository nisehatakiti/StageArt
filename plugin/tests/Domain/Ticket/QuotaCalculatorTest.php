<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Ticket;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Ticket\QuotaCalculator;

final class QuotaCalculatorTest extends TestCase
{
    public function test_quota_disabled_has_no_shortfall(): void
    {
        $this->assertSame(0, QuotaCalculator::shortfall(false, 100, 10));
    }

    public function test_quota_enabled_and_met_has_no_shortfall(): void
    {
        $this->assertSame(0, QuotaCalculator::shortfall(true, 20, 25));
    }

    public function test_quota_enabled_and_unmet_computes_shortfall(): void
    {
        $this->assertSame(5, QuotaCalculator::shortfall(true, 20, 15));
    }

    public function test_buyback_off_never_produces_a_payable_amount(): void
    {
        $this->assertSame(0, QuotaCalculator::shortfallPayable(false, 1000, 5));
    }

    public function test_buyback_on_computes_shortfall_times_unit_price(): void
    {
        $this->assertSame(5000, QuotaCalculator::shortfallPayable(true, 1000, 5));
    }

    public function test_buyback_on_with_zero_shortfall_is_zero(): void
    {
        $this->assertSame(0, QuotaCalculator::shortfallPayable(true, 1000, 0));
    }

    public function test_buyback_on_with_null_unit_price_is_zero(): void
    {
        $this->assertSame(0, QuotaCalculator::shortfallPayable(true, null, 5));
    }
}
