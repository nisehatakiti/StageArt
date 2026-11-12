<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Ticket;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Ticket\SalesEndRule;

final class SalesEndRuleTest extends TestCase
{
    public function test_day_before_at_time_computes_the_previous_days_deadline(): void
    {
        $rule = SalesEndRule::dayBeforeAtTime('23:00');

        $deadline = $rule->computeDeadline(new DateTimeImmutable('2026-10-10 18:00:00'));

        $this->assertSame('2026-10-09 23:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_hours_before_start_computes_the_offset_deadline(): void
    {
        $rule = SalesEndRule::hoursBeforeStart(3);

        $deadline = $rule->computeDeadline(new DateTimeImmutable('2026-10-10 18:00:00'));

        $this->assertSame('2026-10-10 15:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function test_day_before_at_time_rejects_invalid_time_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalesEndRule::dayBeforeAtTime('25:99');
    }

    public function test_hours_before_start_rejects_non_positive_hours(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalesEndRule::hoursBeforeStart(0);
    }

    public function test_from_stored_reconstructs_day_before_at_time(): void
    {
        $rule = SalesEndRule::fromStored(SalesEndRule::DAY_BEFORE_AT_TIME, '20:30');

        $this->assertSame(SalesEndRule::DAY_BEFORE_AT_TIME, $rule->rule());
        $this->assertSame('20:30', $rule->parameter());
    }

    public function test_from_stored_reconstructs_hours_before_start(): void
    {
        $rule = SalesEndRule::fromStored(SalesEndRule::HOURS_BEFORE_START, '5');

        $this->assertSame(SalesEndRule::HOURS_BEFORE_START, $rule->rule());
        $this->assertSame('5', $rule->parameter());
    }

    public function test_from_stored_rejects_unknown_rule(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SalesEndRule::fromStored('NOT_A_RULE', '3');
    }
}
