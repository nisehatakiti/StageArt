<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Rehearsal;

use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Rehearsal\RehearsalDateTime;

/**
 * PR #7 R10 (docs/20-RehearsalManagementScreen.md §23.3): Rehearsal
 * date/time input is an ISO 8601 instant, held in the Rehearsal's
 * timezone; a value without an offset is wall-clock time in that zone;
 * anything else is rejected.
 */
final class RehearsalDateTimeTest extends TestCase
{
    private function tokyo(): DateTimeZone
    {
        return new DateTimeZone('Asia/Tokyo');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function sameInstant(): array
    {
        return [
            'JST' => ['2026-11-11T10:00:00+09:00'],
            'UTC Z' => ['2026-11-11T01:00:00Z'],
            'UTC +00:00' => ['2026-11-11T01:00:00+00:00'],
            'New York winter, previous day' => ['2026-11-10T20:00:00-05:00'],
            'offset without colon' => ['2026-11-10T20:00:00-0500'],
            'fractional seconds' => ['2026-11-11T01:00:00.000Z'],
            'no seconds' => ['2026-11-11T10:00+09:00'],
        ];
    }

    /**
     * @dataProvider sameInstant
     */
    public function test_the_same_instant_with_any_offset_is_the_same_tokyo_time(string $value): void
    {
        $parsed = RehearsalDateTime::parseOptional($value, $this->tokyo());

        $this->assertSame('2026-11-11T10:00:00+09:00', $parsed->format(DATE_ATOM));
        $this->assertSame('Asia/Tokyo', $parsed->getTimezone()->getName());
    }

    public function test_daylight_saving_offsets_are_the_instant_they_name(): void
    {
        // New York in July is -04:00 (EDT): 06:00 EDT = 19:00 JST.
        $summer = RehearsalDateTime::parseOptional('2026-07-01T06:00:00-04:00', $this->tokyo());
        // London in July is +01:00 (BST): 11:00 BST = 19:00 JST.
        $london = RehearsalDateTime::parseOptional('2026-07-01T11:00:00+01:00', $this->tokyo());

        $this->assertSame('2026-07-01T19:00:00+09:00', $summer->format(DATE_ATOM));
        $this->assertSame('2026-07-01T19:00:00+09:00', $london->format(DATE_ATOM));
    }

    public function test_crossing_the_date_boundary(): void
    {
        // 23:30 on the 10th in UTC is 08:30 on the 11th in Tokyo.
        $this->assertSame(
            '2026-11-11T08:30:00+09:00',
            RehearsalDateTime::parseOptional('2026-11-10T23:30:00Z', $this->tokyo())->format(DATE_ATOM)
        );
        // 00:30 JST on the 1st of the year is still the previous year in UTC.
        $this->assertSame(
            '2026-01-01T00:30:00+09:00',
            RehearsalDateTime::parseOptional('2025-12-31T15:30:00Z', $this->tokyo())->format(DATE_ATOM)
        );
    }

    public function test_a_value_without_an_offset_is_wall_clock_time_in_the_rehearsal_timezone(): void
    {
        $this->assertSame(
            '2026-11-11T10:00:00+09:00',
            RehearsalDateTime::parseOptional('2026-11-11T10:00:00', $this->tokyo())->format(DATE_ATOM)
        );
        $this->assertSame(
            '2026-11-11T10:00:00+09:00',
            RehearsalDateTime::parseOptional('2026-11-11 10:00:00', $this->tokyo())->format(DATE_ATOM)
        );
        $this->assertSame(
            '2026-11-11T10:00:00-05:00',
            RehearsalDateTime::parseOptional('2026-11-11T10:00:00', new DateTimeZone('America/New_York'))->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalid(): array
    {
        return [
            'text' => ['not a date'],
            'relative' => ['+3 days'],
            'date only' => ['2026-11-11'],
            'impossible day' => ['2026-02-30T10:00:00+09:00'],
            'month 13' => ['2026-13-01T10:00:00+09:00'],
            'hour 24' => ['2026-11-11T24:00:00+09:00'],
            'minute 60' => ['2026-11-11T10:60:00+09:00'],
            'named zone' => ['2026-11-11T10:00:00 JST'],
            'trailing junk' => ['2026-11-11T10:00:00+09:00x'],
        ];
    }

    /**
     * @dataProvider invalid
     */
    public function test_an_invalid_value_is_rejected(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);

        RehearsalDateTime::parseOptional($value, $this->tokyo());
    }

    public function test_empty_and_required(): void
    {
        $this->assertNull(RehearsalDateTime::parseOptional(null, $this->tokyo()));
        $this->assertNull(RehearsalDateTime::parseOptional('', $this->tokyo()));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Rehearsal start date/time is required.');
        RehearsalDateTime::parseRequired('', $this->tokyo(), 'start date/time');
    }

    public function test_zone_defaults_to_tokyo_and_rejects_unknown_names(): void
    {
        $this->assertSame('Asia/Tokyo', RehearsalDateTime::zone(null)->getName());
        $this->assertSame('Asia/Tokyo', RehearsalDateTime::zone('  ')->getName());
        $this->assertSame('America/New_York', RehearsalDateTime::zone('America/New_York')->getName());

        $this->expectException(InvalidArgumentException::class);
        RehearsalDateTime::zone('Mars/Olympus_Mons');
    }
}
