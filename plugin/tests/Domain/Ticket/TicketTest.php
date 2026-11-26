<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Ticket;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Ticket\Ticket;
use StageArt\Domain\Ticket\TicketStatus;

final class TicketTest extends TestCase
{
    public function test_create_starts_active(): void
    {
        $ticket = Ticket::create(ProductionId::generate(), '一般', 5000, null);

        $this->assertSame(TicketStatus::ACTIVE, $ticket->status()->toString());
        $this->assertSame('一般', $ticket->name());
        $this->assertSame(5000, $ticket->price());
    }

    public function test_create_rejects_zero_price(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ticket::create(ProductionId::generate(), '招待', 0, null);
    }

    public function test_create_rejects_negative_price(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ticket::create(ProductionId::generate(), '一般', -100, null);
    }

    public function test_create_rejects_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Ticket::create(ProductionId::generate(), '   ', 3000, null);
    }

    public function test_update_basic_info_changes_fields(): void
    {
        $ticket = Ticket::create(ProductionId::generate(), '一般', 5000, null);

        $ticket->updateBasicInfo('学生', 3000, '要学生証');

        $this->assertSame('学生', $ticket->name());
        $this->assertSame(3000, $ticket->price());
        $this->assertSame('要学生証', $ticket->remarks());
    }

    public function test_update_basic_info_rejects_zero_price(): void
    {
        $ticket = Ticket::create(ProductionId::generate(), '一般', 5000, null);

        $this->expectException(InvalidArgumentException::class);
        $ticket->updateBasicInfo('一般', 0, null);
    }

    public function test_archive_sets_archived_status(): void
    {
        $ticket = Ticket::create(ProductionId::generate(), '一般', 5000, null);

        $ticket->archive();

        $this->assertSame(TicketStatus::ARCHIVED, $ticket->status()->toString());
        $this->assertFalse($ticket->isActive());
    }

    public function test_archive_rejects_already_archived_ticket(): void
    {
        $ticket = Ticket::create(ProductionId::generate(), '一般', 5000, null);
        $ticket->archive();

        $this->expectException(InvalidArgumentException::class);
        $ticket->archive();
    }

    public function test_update_basic_info_rejects_archived_ticket(): void
    {
        $ticket = Ticket::create(ProductionId::generate(), '一般', 5000, null);
        $ticket->archive();

        $this->expectException(InvalidArgumentException::class);
        $ticket->updateBasicInfo('一般', 4000, null);
    }
}
