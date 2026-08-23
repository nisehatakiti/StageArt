<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Rehearsal;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalStatus;

final class RehearsalTest extends TestCase
{
    public function test_create_starts_scheduled(): void
    {
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1 Run', null, null, null, null, null);

        $this->assertSame(RehearsalStatus::SCHEDULED, $rehearsal->status()->toString());
        $this->assertSame('Act 1 Run', $rehearsal->title());
    }

    public function test_full_lifecycle_transitions(): void
    {
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1', null, null, null, null, null);

        $rehearsal->confirm();
        $this->assertSame(RehearsalStatus::CONFIRMED, $rehearsal->status()->toString());

        $rehearsal->activate();
        $this->assertSame(RehearsalStatus::ACTIVE, $rehearsal->status()->toString());

        $rehearsal->complete();
        $this->assertSame(RehearsalStatus::COMPLETED, $rehearsal->status()->toString());
    }

    public function test_confirm_rejects_non_scheduled_rehearsal(): void
    {
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1', null, null, null, null, null);
        $rehearsal->confirm();

        $this->expectException(InvalidArgumentException::class);
        $rehearsal->confirm();
    }

    public function test_activate_rejects_non_confirmed_rehearsal(): void
    {
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1', null, null, null, null, null);

        $this->expectException(InvalidArgumentException::class);
        $rehearsal->activate();
    }

    public function test_cancel_is_allowed_from_scheduled(): void
    {
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1', null, null, null, null, null);
        $rehearsal->cancel();

        $this->assertSame(RehearsalStatus::CANCELLED, $rehearsal->status()->toString());
    }

    public function test_cancel_rejects_completed_rehearsal(): void
    {
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1', null, null, null, null, null);
        $rehearsal->confirm();
        $rehearsal->activate();
        $rehearsal->complete();

        $this->expectException(InvalidArgumentException::class);
        $rehearsal->cancel();
    }

    public function test_update_basic_info_rejects_terminal_rehearsal(): void
    {
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1', null, null, null, null, null);
        $rehearsal->cancel();

        $this->expectException(InvalidArgumentException::class);
        $rehearsal->updateBasicInfo('New Title', null, null, null, null, null);
    }
}
