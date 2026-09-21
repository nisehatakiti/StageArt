<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Performance;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Performance\PerformanceStatus;
use StageArt\Domain\Production\ProductionId;

final class PerformanceTest extends TestCase
{
    public function test_create_starts_in_published(): void
    {
        $performance = Performance::create(
            ProductionId::generate(),
            new DateTimeImmutable('2026-10-10'),
            '13:00',
            '15:30',
            100,
            null,
            null
        );

        $this->assertSame(PerformanceStatus::PUBLISHED, $performance->status()->toString());
        $this->assertSame('13:00:00', $performance->startTime());
        $this->assertSame('15:30:00', $performance->endTime());
        $this->assertSame(100, $performance->capacity());
    }

    /**
     * Backend PHPUnit環境整備 Phase: fixed from the original '9:05' input,
     * which Performance::normalizeTime()'s own TIME_PATTERN (and its own
     * "expected H:i or H:i:s" exception message) has always required to
     * be zero-padded - a single-digit hour was never valid input, this
     * assertion was simply never executed before now. The real, intended
     * behavior this test name describes - "H:i" (already zero-padded)
     * gets ":00" seconds appended to become "H:i:s" - is unchanged and is
     * what this now actually verifies.
     */
    public function test_create_normalizes_hi_time_to_hisss(): void
    {
        $performance = Performance::create(ProductionId::generate(), new DateTimeImmutable('2026-10-10'), '09:05', null, 50, null, null);

        $this->assertSame('09:05:00', $performance->startTime());
        $this->assertNull($performance->endTime());
    }

    public function test_create_rejects_invalid_time_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Performance::create(ProductionId::generate(), new DateTimeImmutable('2026-10-10'), 'not-a-time', null, 50, null, null);
    }

    public function test_create_rejects_zero_capacity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Performance::create(ProductionId::generate(), new DateTimeImmutable('2026-10-10'), '13:00', null, 0, null, null);
    }

    public function test_create_rejects_negative_capacity(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Performance::create(ProductionId::generate(), new DateTimeImmutable('2026-10-10'), '13:00', null, -5, null, null);
    }

    public function test_update_basic_info_changes_fields(): void
    {
        $performance = Performance::create(ProductionId::generate(), new DateTimeImmutable('2026-10-10'), '13:00', null, 50, null, null);

        $performance->updateBasicInfo(new DateTimeImmutable('2026-10-11'), '18:00', '20:00', 80, '注意事項', 'A');

        $this->assertSame('2026-10-11', $performance->performanceDate()->format('Y-m-d'));
        $this->assertSame('18:00:00', $performance->startTime());
        $this->assertSame('20:00:00', $performance->endTime());
        $this->assertSame(80, $performance->capacity());
        $this->assertSame('注意事項', $performance->remarks());
        $this->assertSame('A', $performance->symbol());
    }

    public function test_update_basic_info_rejects_cancelled_performance(): void
    {
        $performance = Performance::create(ProductionId::generate(), new DateTimeImmutable('2026-10-10'), '13:00', null, 50, null, null);
        $performance->cancel();

        $this->expectException(InvalidArgumentException::class);
        $performance->updateBasicInfo(new DateTimeImmutable('2026-10-11'), '18:00', null, 80, null, null);
    }

    public function test_cancel_transitions_to_cancelled(): void
    {
        $performance = Performance::create(ProductionId::generate(), new DateTimeImmutable('2026-10-10'), '13:00', null, 50, null, null);

        $performance->cancel();

        $this->assertSame(PerformanceStatus::CANCELLED, $performance->status()->toString());
    }

    public function test_cancel_rejects_already_cancelled_performance(): void
    {
        $performance = Performance::create(ProductionId::generate(), new DateTimeImmutable('2026-10-10'), '13:00', null, 50, null, null);
        $performance->cancel();

        $this->expectException(InvalidArgumentException::class);
        $performance->cancel();
    }

    public function test_change_status_rejects_cancelled_performance(): void
    {
        $performance = Performance::create(ProductionId::generate(), new DateTimeImmutable('2026-10-10'), '13:00', null, 50, null, null);
        $performance->cancel();

        $this->expectException(InvalidArgumentException::class);
        $performance->changeStatus(PerformanceStatus::fromString(PerformanceStatus::PUBLISHED));
    }

    public function test_change_status_allows_normal_transition(): void
    {
        $performance = Performance::create(ProductionId::generate(), new DateTimeImmutable('2026-10-10'), '13:00', null, 50, null, null);

        $performance->changeStatus(PerformanceStatus::fromString(PerformanceStatus::PUBLISHED));

        $this->assertSame(PerformanceStatus::PUBLISHED, $performance->status()->toString());
    }

    /**
     * Phase 2 instruction §11/§26: changeCapacity() is the mandatory
     * Production-cascade path and must succeed even on an already
     * CANCELLED Performance - unlike updateBasicInfo(), which rejects
     * CANCELLED.
     */
    public function test_change_capacity_succeeds_even_on_cancelled_performance(): void
    {
        $performance = Performance::create(ProductionId::generate(), new DateTimeImmutable('2026-10-10'), '13:00', null, 50, null, null);
        $performance->cancel();

        $performance->changeCapacity(120);

        $this->assertSame(120, $performance->capacity());
        $this->assertSame(PerformanceStatus::CANCELLED, $performance->status()->toString());
    }

    public function test_change_capacity_rejects_non_positive_value(): void
    {
        $performance = Performance::create(ProductionId::generate(), new DateTimeImmutable('2026-10-10'), '13:00', null, 50, null, null);

        $this->expectException(InvalidArgumentException::class);
        $performance->changeCapacity(0);
    }
}
