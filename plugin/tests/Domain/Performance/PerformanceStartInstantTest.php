<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Performance;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Reservation\ReservationModificationPolicy;
use StageArt\Domain\Ticket\SalesEndRule;

/**
 * PR #7 R14-A (F13): a Performance's start is the Asia/Tokyo wall clock it
 * was entered as, whatever PHP's default timezone is (WordPress sets UTC).
 * Before R14-A, 19:00 was read as 19:00 UTC = 04:00 JST the next day, so
 * the 開演後 cutoffs and the sales-end deadline ran 9 hours late.
 */
final class PerformanceStartInstantTest extends TestCase
{
    private string $defaultTimezone;

    protected function setUp(): void
    {
        $this->defaultTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->defaultTimezone);
    }

    private static function performance(string $date, string $time): Performance
    {
        return Performance::create(ProductionId::generate(), new DateTimeImmutable($date), $time, null, 50, null, null);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function starts(): array
    {
        return [
            'evening' => ['2026-12-01', '19:00', '2026-12-01T10:00:00+00:00'],
            'just after midnight (previous UTC day)' => ['2026-12-02', '00:30', '2026-12-01T15:30:00+00:00'],
            'new year (previous UTC year)' => ['2027-01-01', '00:30', '2026-12-31T15:30:00+00:00'],
            'with seconds' => ['2026-12-01', '19:00:00', '2026-12-01T10:00:00+00:00'],
        ];
    }

    /**
     * @dataProvider starts
     */
    public function test_the_start_is_the_same_instant_in_any_php_default_timezone(string $date, string $time, string $utc): void
    {
        foreach (['UTC', 'America/New_York', 'Asia/Tokyo', 'Pacific/Kiritimati'] as $phpTimezone) {
            date_default_timezone_set($phpTimezone);
            $start = self::performance($date, $time)->startDateTime();

            $this->assertSame($utc, $start->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM), $phpTimezone);
            $this->assertSame('+09:00', $start->format('P'), $phpTimezone);
        }
    }

    public function test_the_after_start_cutoff_is_at_the_jst_start(): void
    {
        date_default_timezone_set('UTC');
        $start = self::performance('2026-12-01', '19:00')->startDateTime();

        $justBefore = new DateTimeImmutable('2026-12-01T18:59:00+09:00');
        $justAfter = new DateTimeImmutable('2026-12-01T19:00:00+09:00');

        $this->assertTrue($justBefore < $start);
        $this->assertFalse($justAfter < $start);
        $this->assertTrue(ReservationModificationPolicy::canDecreaseGuestCount($justBefore, $start));
        $this->assertFalse(ReservationModificationPolicy::canDecreaseGuestCount($justAfter, $start));
        // 9 hours later (what the UTC reading allowed before R14-A) is refused.
        $this->assertFalse(ReservationModificationPolicy::canDecreaseGuestCount(new DateTimeImmutable('2026-12-02T03:59:00+09:00'), $start));
    }

    /**
     * @return array<string, array{string, string, string, string, string}>
     */
    public static function salesEnds(): array
    {
        return [
            '3 hours before 19:00' => ['2026-12-01', '19:00', 'HOURS_BEFORE_START', '3', '2026-12-01T16:00:00+09:00'],
            'day before at 23:00' => ['2026-12-01', '19:00', 'DAY_BEFORE_AT_TIME', '23:00', '2026-11-30T23:00:00+09:00'],
            'day before at 23:00 across the year' => ['2027-01-01', '14:00', 'DAY_BEFORE_AT_TIME', '23:00', '2026-12-31T23:00:00+09:00'],
            '1 hour before 00:30 (previous day)' => ['2026-12-02', '00:30', 'HOURS_BEFORE_START', '1', '2026-12-01T23:30:00+09:00'],
        ];
    }

    /**
     * @dataProvider salesEnds
     */
    public function test_the_sales_end_deadline_is_computed_from_the_jst_start(string $date, string $time, string $rule, string $parameter, string $expected): void
    {
        foreach (['UTC', 'America/New_York', 'Asia/Tokyo'] as $phpTimezone) {
            date_default_timezone_set($phpTimezone);
            $deadline = SalesEndRule::fromStored($rule, $parameter)->computeDeadline(self::performance($date, $time)->startDateTime());

            $this->assertSame($expected, $deadline->format(DATE_ATOM), $phpTimezone);
        }
    }
}
