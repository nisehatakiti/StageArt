<?php

declare(strict_types=1);

namespace StageArt\Tests\Presentation\Print;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use StageArt\Presentation\Print\PrintViewHtmlRenderer;

/**
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §25): the print view shows
 * an item that ends after midnight as "翌00:30", like the app's list.
 */
final class PrintViewEndTimeTest extends TestCase
{
    private function endTime(string $start, string $end): string
    {
        $method = new ReflectionMethod(PrintViewHtmlRenderer::class, 'endTime');
        $method->setAccessible(true);

        return $method->invoke(new PrintViewHtmlRenderer(), $start, $end);
    }

    public function test_end_time_relative_to_the_start_day(): void
    {
        $this->assertSame('12:00', $this->endTime('2026-11-10T10:00:00+09:00', '2026-11-10T12:00:00+09:00'));
        $this->assertSame('翌00:30', $this->endTime('2026-11-10T23:30:00+09:00', '2026-11-11T00:30:00+09:00'));
        $this->assertSame('翌01:30', $this->endTime('2026-12-31T23:30:00+09:00', '2027-01-01T01:30:00+09:00'));
        $this->assertSame('11/12 09:00', $this->endTime('2026-11-10T23:30:00+09:00', '2026-11-12T09:00:00+09:00'));
        // Same instant written in UTC: still the next JST day.
        $this->assertSame('翌00:30', $this->endTime('2026-11-10T23:30:00+09:00', '2026-11-10T15:30:00Z'));
    }
}
