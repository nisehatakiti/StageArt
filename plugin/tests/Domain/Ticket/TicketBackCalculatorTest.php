<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Ticket;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Ticket\TicketBackCalculator;
use StageArt\Domain\Ticket\TicketBackCondition;
use StageArt\Domain\Ticket\TicketBackMode;

/**
 * Worked examples straight from Chapter 32 §4.3/§4.4 and Phase 3
 * instruction §27: 21枚以上->20%／11枚以上->15%／1枚以上->10% (progressive,
 * first-match-by-priority), and 1-10枚->10%／11-20枚->20%／21枚~->30%
 * (separated, contiguous bands).
 */
final class TicketBackCalculatorTest extends TestCase
{
    public function test_progressive_applies_the_first_matching_priority_to_the_whole_quantity(): void
    {
        $conditions = [
            new TicketBackCondition(1, 21, TicketBackCondition::COMPARATOR_GTE, 20),
            new TicketBackCondition(2, 11, TicketBackCondition::COMPARATOR_GTE, 15),
            new TicketBackCondition(3, 1, TicketBackCondition::COMPARATOR_GTE, 10),
        ];

        $amount = TicketBackCalculator::calculate(
            TicketBackMode::fromString(TicketBackMode::PROGRESSIVE),
            $conditions,
            15,
            1000
        );

        // 15 * 1000 * 15% (priority 2 matches: 15 >= 11, but not >= 21)
        $this->assertSame(2250, $amount);
    }

    public function test_progressive_with_21_tickets_matches_the_highest_priority_condition(): void
    {
        $conditions = [
            new TicketBackCondition(1, 21, TicketBackCondition::COMPARATOR_GTE, 20),
            new TicketBackCondition(2, 11, TicketBackCondition::COMPARATOR_GTE, 15),
            new TicketBackCondition(3, 1, TicketBackCondition::COMPARATOR_GTE, 10),
        ];

        $amount = TicketBackCalculator::calculate(
            TicketBackMode::fromString(TicketBackMode::PROGRESSIVE),
            $conditions,
            21,
            1000
        );

        $this->assertSame(4200, $amount); // 21 * 1000 * 20%
    }

    public function test_progressive_below_every_threshold_yields_zero(): void
    {
        $conditions = [
            new TicketBackCondition(1, 11, TicketBackCondition::COMPARATOR_GTE, 15),
        ];

        $amount = TicketBackCalculator::calculate(
            TicketBackMode::fromString(TicketBackMode::PROGRESSIVE),
            $conditions,
            5,
            1000
        );

        $this->assertSame(0, $amount);
    }

    public function test_separated_accumulates_per_band_rates(): void
    {
        $conditions = [
            new TicketBackCondition(1, 1, TicketBackCondition::COMPARATOR_GTE, 10),
            new TicketBackCondition(2, 11, TicketBackCondition::COMPARATOR_GTE, 20),
            new TicketBackCondition(3, 21, TicketBackCondition::COMPARATOR_GTE, 30),
        ];

        $amount = TicketBackCalculator::calculate(
            TicketBackMode::fromString(TicketBackMode::SEPARATED),
            $conditions,
            15,
            1000
        );

        // band 1-10 @10%: 10*1000*0.10=1000; band 11-15 @20%: 5*1000*0.20=1000
        $this->assertSame(2000, $amount);
    }

    public function test_separated_with_sales_spanning_all_three_bands(): void
    {
        $conditions = [
            new TicketBackCondition(1, 1, TicketBackCondition::COMPARATOR_GTE, 10),
            new TicketBackCondition(2, 11, TicketBackCondition::COMPARATOR_GTE, 20),
            new TicketBackCondition(3, 21, TicketBackCondition::COMPARATOR_GTE, 30),
        ];

        $amount = TicketBackCalculator::calculate(
            TicketBackMode::fromString(TicketBackMode::SEPARATED),
            $conditions,
            25,
            1000
        );

        // 1-10 @10%: 1000; 11-20 @20%: 2000; 21-25 @30%: 5*1000*0.30=1500
        $this->assertSame(4500, $amount);
    }

    public function test_zero_sold_count_yields_zero_regardless_of_mode(): void
    {
        $conditions = [new TicketBackCondition(1, 1, TicketBackCondition::COMPARATOR_GTE, 10)];

        $this->assertSame(0, TicketBackCalculator::calculate(TicketBackMode::fromString(TicketBackMode::PROGRESSIVE), $conditions, 0, 1000));
        $this->assertSame(0, TicketBackCalculator::calculate(TicketBackMode::fromString(TicketBackMode::SEPARATED), $conditions, 0, 1000));
    }
}
