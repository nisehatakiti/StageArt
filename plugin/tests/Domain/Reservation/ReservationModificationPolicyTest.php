<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Reservation;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Reservation\ReservationModificationPolicy;

/**
 * Phase 3 instruction §16/§29/§62 - the three-case matrix verbatim.
 */
final class ReservationModificationPolicyTest extends TestCase
{
    private DateTimeImmutable $salesEndAt;
    private DateTimeImmutable $performanceStartAt;

    protected function setUp(): void
    {
        $this->salesEndAt = new DateTimeImmutable('2026-10-09 23:00:00');
        $this->performanceStartAt = new DateTimeImmutable('2026-10-10 18:00:00');
    }

    // Case A: before sales end
    public function test_case_a_before_sales_end_allows_increase(): void
    {
        $now = new DateTimeImmutable('2026-10-01 00:00:00');
        $this->assertTrue(ReservationModificationPolicy::canIncreaseGuestCount($now, $this->salesEndAt, $this->performanceStartAt));
    }

    public function test_case_a_before_sales_end_allows_decrease(): void
    {
        $now = new DateTimeImmutable('2026-10-01 00:00:00');
        $this->assertTrue(ReservationModificationPolicy::canDecreaseGuestCount($now, $this->performanceStartAt));
    }

    public function test_case_a_before_sales_end_allows_cancel(): void
    {
        $now = new DateTimeImmutable('2026-10-01 00:00:00');
        $this->assertTrue(ReservationModificationPolicy::canCancel($now, $this->performanceStartAt));
    }

    // Case B: after sales end, before performance start
    public function test_case_b_after_sales_end_forbids_increase(): void
    {
        $now = new DateTimeImmutable('2026-10-10 00:00:00');
        $this->assertFalse(ReservationModificationPolicy::canIncreaseGuestCount($now, $this->salesEndAt, $this->performanceStartAt));
    }

    public function test_case_b_after_sales_end_allows_decrease(): void
    {
        $now = new DateTimeImmutable('2026-10-10 00:00:00');
        $this->assertTrue(ReservationModificationPolicy::canDecreaseGuestCount($now, $this->performanceStartAt));
    }

    public function test_case_b_after_sales_end_allows_cancel(): void
    {
        $now = new DateTimeImmutable('2026-10-10 00:00:00');
        $this->assertTrue(ReservationModificationPolicy::canCancel($now, $this->performanceStartAt));
    }

    // Case C: after performance start
    public function test_case_c_after_start_forbids_increase(): void
    {
        $now = new DateTimeImmutable('2026-10-10 19:00:00');
        $this->assertFalse(ReservationModificationPolicy::canIncreaseGuestCount($now, $this->salesEndAt, $this->performanceStartAt));
    }

    public function test_case_c_after_start_forbids_decrease(): void
    {
        $now = new DateTimeImmutable('2026-10-10 19:00:00');
        $this->assertFalse(ReservationModificationPolicy::canDecreaseGuestCount($now, $this->performanceStartAt));
    }

    public function test_case_c_after_start_forbids_cancel(): void
    {
        $now = new DateTimeImmutable('2026-10-10 19:00:00');
        $this->assertFalse(ReservationModificationPolicy::canCancel($now, $this->performanceStartAt));
    }

    public function test_boundary_exactly_at_sales_end_forbids_increase(): void
    {
        $this->assertFalse(ReservationModificationPolicy::canIncreaseGuestCount($this->salesEndAt, $this->salesEndAt, $this->performanceStartAt));
    }

    public function test_boundary_exactly_at_performance_start_forbids_everything(): void
    {
        $this->assertFalse(ReservationModificationPolicy::canDecreaseGuestCount($this->performanceStartAt, $this->performanceStartAt));
        $this->assertFalse(ReservationModificationPolicy::canCancel($this->performanceStartAt, $this->performanceStartAt));
    }
}
