<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Settlement;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Settlement\ProductionMemberSettlement;

final class ProductionMemberSettlementTest extends TestCase
{
    public function test_open_for_starts_with_zero_settled_amount(): void
    {
        $settlement = ProductionMemberSettlement::openFor(ProductionId::generate(), PersonId::generate());

        $this->assertSame(0, $settlement->totalSettledAmount());
        $this->assertNull($settlement->lastSettledBy());
        $this->assertNull($settlement->lastSettledAt());
    }

    public function test_record_settlement_accumulates_the_total(): void
    {
        $settlement = ProductionMemberSettlement::openFor(ProductionId::generate(), PersonId::generate());
        $settledBy = PersonId::generate();

        $settlement->recordSettlement(5000, $settledBy);
        $settlement->recordSettlement(2000, $settledBy);

        $this->assertSame(7000, $settlement->totalSettledAmount());
        $this->assertTrue($settlement->lastSettledBy()->equals($settledBy));
        $this->assertNotNull($settlement->lastSettledAt());
    }

    public function test_record_settlement_rejects_a_non_positive_amount(): void
    {
        $settlement = ProductionMemberSettlement::openFor(ProductionId::generate(), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);
        $settlement->recordSettlement(0, PersonId::generate());
    }

    public function test_cancel_last_settlement_reverses_only_the_most_recent_amount(): void
    {
        $settlement = ProductionMemberSettlement::openFor(ProductionId::generate(), PersonId::generate());
        $settledBy = PersonId::generate();

        $settlement->recordSettlement(5000, $settledBy);
        $settlement->recordSettlement(2000, $settledBy);

        $cancelledBy = PersonId::generate();
        $settlement->cancelLastSettlement($cancelledBy);

        $this->assertSame(5000, $settlement->totalSettledAmount());
        $this->assertSame(0, $settlement->lastSettledAmount());
        $this->assertTrue($settlement->lastSettledBy()->equals($cancelledBy));
    }

    public function test_cancel_last_settlement_rejects_when_nothing_to_cancel(): void
    {
        $settlement = ProductionMemberSettlement::openFor(ProductionId::generate(), PersonId::generate());

        $this->expectException(InvalidArgumentException::class);
        $settlement->cancelLastSettlement(PersonId::generate());
    }

    public function test_cancel_last_settlement_cannot_be_called_twice_in_a_row(): void
    {
        $settlement = ProductionMemberSettlement::openFor(ProductionId::generate(), PersonId::generate());
        $settlement->recordSettlement(3000, PersonId::generate());

        $settlement->cancelLastSettlement(PersonId::generate());

        $this->expectException(InvalidArgumentException::class);
        $settlement->cancelLastSettlement(PersonId::generate());
    }
}
