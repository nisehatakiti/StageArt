<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\TimetableItem;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\TimetableItem\TimetableItemDateTime;

/**
 * PR #7 R11: Timetable Item times are Asia/Tokyo business times
 * (docs/20-RehearsalManagementScreen.md §24). The PHP default timezone is
 * set to something other than Asia/Tokyo in each test, as WordPress does
 * (UTC), so nothing here passes only because the machine runs in JST.
 */
final class TimetableItemDateTimeTest extends TestCase
{
    private string $previousTimezone;

    protected function setUp(): void
    {
        $this->previousTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public function sameInstantProvider(): array
    {
        return [
            '+09:00' => ['2026-11-11T10:00:00+09:00', '2026-11-11T10:00:00+09:00'],
            'Z' => ['2026-11-11T01:00:00Z', '2026-11-11T10:00:00+09:00'],
            '-05:00' => ['2026-11-10T20:00:00-05:00', '2026-11-11T10:00:00+09:00'],
            'no offset = Asia/Tokyo wall-clock' => ['2026-11-11T10:00:00', '2026-11-11T10:00:00+09:00'],
            'no offset, space, no seconds' => ['2026-11-11 10:00', '2026-11-11T10:00:00+09:00'],
            'UTC evening is next JST day' => ['2026-11-10T15:30:00Z', '2026-11-11T00:30:00+09:00'],
            'year end UTC is new year JST' => ['2026-12-31T15:00:00Z', '2027-01-01T00:00:00+09:00'],
            'new year JST stays new year' => ['2027-01-01T00:00:00+09:00', '2027-01-01T00:00:00+09:00'],
            'just before JST midnight' => ['2026-12-31T23:59:00+09:00', '2026-12-31T23:59:00+09:00'],
        ];
    }

    /**
     * @dataProvider sameInstantProvider
     */
    public function test_values_are_read_as_asia_tokyo_times(string $input, string $expected): void
    {
        $this->assertSame($expected, TimetableItemDateTime::parseRequired($input, 'start_date_time')->format(DATE_ATOM));
    }

    public function test_the_api_value_sent_back_unchanged_gives_the_same_time(): void
    {
        $first = TimetableItemDateTime::parseRequired('2026-08-20T10:00:00+09:00', 'start_date_time')->format(DATE_ATOM);
        $second = TimetableItemDateTime::parseRequired($first, 'start_date_time')->format(DATE_ATOM);
        $third = TimetableItemDateTime::parseRequired($second, 'start_date_time')->format(DATE_ATOM);

        $this->assertSame('2026-08-20T10:00:00+09:00', $third);
    }

    public function test_the_result_does_not_depend_on_the_php_default_timezone(): void
    {
        foreach (['UTC', 'America/New_York', 'Asia/Tokyo', 'Pacific/Kiritimati'] as $timezone) {
            date_default_timezone_set($timezone);
            $this->assertSame(
                '2026-08-20T10:00:00+09:00',
                TimetableItemDateTime::parseRequired('2026-08-20T10:00:00', 'start_date_time')->format(DATE_ATOM),
                $timezone
            );
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public function invalidProvider(): array
    {
        return [
            'not a date' => ['not a date'],
            'relative' => ['+3 days'],
            'tomorrow' => ['tomorrow'],
            'no time' => ['2026-11-11'],
            'February 30' => ['2026-02-30T10:00:00+09:00'],
            'hour 24' => ['2026-11-11T24:00:00+09:00'],
            'minute 60' => ['2026-11-11T10:60:00+09:00'],
            'time only' => ['10:00'],
        ];
    }

    /**
     * @dataProvider invalidProvider
     */
    public function test_invalid_values_are_rejected(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);

        TimetableItemDateTime::parseRequired($input, 'start_date_time');
    }

    public function test_a_missing_start_is_rejected_instead_of_meaning_now(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('start_date_time is required');

        TimetableItemDateTime::parseRequired('', 'start_date_time');
    }

    public function test_an_optional_empty_value_is_null(): void
    {
        $this->assertNull(TimetableItemDateTime::parseOptional(null));
        $this->assertNull(TimetableItemDateTime::parseOptional(''));
    }
}
