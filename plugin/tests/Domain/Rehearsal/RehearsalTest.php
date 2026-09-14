<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Rehearsal;

use DateTimeImmutable;
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

    // --- Phase 7 (Rehearsal仕様整合) §3: CONFIRMED/ACTIVEの日付変更禁止 ---

    public function test_date_change_is_allowed_while_scheduled(): void
    {
        $rehearsal = Rehearsal::create(
            ProductionId::generate(),
            'Act 1',
            null,
            new DateTimeImmutable('2026-09-20 18:00'),
            null,
            null,
            null
        );

        $rehearsal->updateBasicInfo('Act 1', null, new DateTimeImmutable('2026-09-21 18:00'), null, null, null);

        $this->assertSame('2026-09-21', $rehearsal->startDateTime()->format('Y-m-d'));
    }

    public function test_date_change_is_rejected_while_confirmed(): void
    {
        $rehearsal = Rehearsal::create(
            ProductionId::generate(),
            'Act 1',
            null,
            new DateTimeImmutable('2026-09-20 18:00'),
            null,
            null,
            null
        );
        $rehearsal->confirm();

        $this->expectException(InvalidArgumentException::class);
        $rehearsal->updateBasicInfo('Act 1', null, new DateTimeImmutable('2026-09-21 18:00'), null, null, null);
    }

    public function test_date_change_is_rejected_while_active(): void
    {
        $rehearsal = Rehearsal::create(
            ProductionId::generate(),
            'Act 1',
            null,
            new DateTimeImmutable('2026-09-20 18:00'),
            null,
            null,
            null
        );
        $rehearsal->confirm();
        $rehearsal->activate();

        $this->expectException(InvalidArgumentException::class);
        $rehearsal->updateBasicInfo('Act 1', null, new DateTimeImmutable('2026-09-21 18:00'), null, null, null);
    }

    public function test_end_date_change_is_also_rejected_while_confirmed(): void
    {
        $rehearsal = Rehearsal::create(
            ProductionId::generate(),
            'Act 1',
            null,
            new DateTimeImmutable('2026-09-20 18:00'),
            new DateTimeImmutable('2026-09-20 20:00'),
            null,
            null
        );
        $rehearsal->confirm();

        $this->expectException(InvalidArgumentException::class);
        $rehearsal->updateBasicInfo(
            'Act 1',
            null,
            new DateTimeImmutable('2026-09-20 18:00'),
            new DateTimeImmutable('2026-09-21 20:00'),
            null,
            null
        );
    }

    public function test_time_only_change_is_allowed_while_confirmed(): void
    {
        $rehearsal = Rehearsal::create(
            ProductionId::generate(),
            'Act 1',
            null,
            new DateTimeImmutable('2026-09-20 18:00'),
            new DateTimeImmutable('2026-09-20 20:00'),
            null,
            null
        );
        $rehearsal->confirm();

        $rehearsal->updateBasicInfo(
            'Act 1',
            null,
            new DateTimeImmutable('2026-09-20 19:00'),
            new DateTimeImmutable('2026-09-20 21:00'),
            null,
            null
        );

        $this->assertSame('19:00:00', $rehearsal->startDateTime()->format('H:i:s'));
    }

    public function test_time_only_change_is_allowed_while_active(): void
    {
        $rehearsal = Rehearsal::create(
            ProductionId::generate(),
            'Act 1',
            null,
            new DateTimeImmutable('2026-09-20 18:00'),
            null,
            null,
            null
        );
        $rehearsal->confirm();
        $rehearsal->activate();

        $rehearsal->updateBasicInfo('Act 1', null, new DateTimeImmutable('2026-09-20 19:00'), null, null, null);

        $this->assertSame('19:00:00', $rehearsal->startDateTime()->format('H:i:s'));
    }

    public function test_date_change_is_rejected_while_completed(): void
    {
        $rehearsal = Rehearsal::create(
            ProductionId::generate(),
            'Act 1',
            null,
            new DateTimeImmutable('2026-09-20 18:00'),
            null,
            null,
            null
        );
        $rehearsal->confirm();
        $rehearsal->activate();
        $rehearsal->complete();

        $this->expectException(InvalidArgumentException::class);
        $rehearsal->updateBasicInfo('Act 1', null, new DateTimeImmutable('2026-09-21 18:00'), null, null, null);
    }

    // --- Phase 7 §4/§5: responseDeadline / reminderSentAt ---

    public function test_new_rehearsal_has_no_response_deadline(): void
    {
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1', null, null, null, null, null);

        $this->assertNull($rehearsal->responseDeadline());
        $this->assertNull($rehearsal->reminderSentAt());
    }

    public function test_create_accepts_an_initial_response_deadline(): void
    {
        $deadline = new DateTimeImmutable('2026-09-19 18:00');
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1', null, null, null, null, null, $deadline);

        $this->assertSame($deadline->getTimestamp(), $rehearsal->responseDeadline()->getTimestamp());
    }

    public function test_change_response_deadline_overwrites_unconditionally(): void
    {
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1', null, null, null, null, null);

        $rehearsal->changeResponseDeadline(new DateTimeImmutable('2026-09-19 18:00'));
        $this->assertNotNull($rehearsal->responseDeadline());

        $rehearsal->changeResponseDeadline(null);
        $this->assertNull($rehearsal->responseDeadline());
    }

    public function test_change_response_deadline_rejects_terminal_rehearsal(): void
    {
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1', null, null, null, null, null);
        $rehearsal->cancel();

        $this->expectException(InvalidArgumentException::class);
        $rehearsal->changeResponseDeadline(new DateTimeImmutable('2026-09-19 18:00'));
    }

    public function test_has_response_deadline_passed(): void
    {
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1', null, null, null, null, null);
        $rehearsal->changeResponseDeadline(new DateTimeImmutable('2026-09-19 18:00'));

        $this->assertFalse($rehearsal->hasResponseDeadlinePassed(new DateTimeImmutable('2026-09-19 17:59')));
        $this->assertTrue($rehearsal->hasResponseDeadlinePassed(new DateTimeImmutable('2026-09-19 18:01')));
    }

    public function test_has_response_deadline_passed_is_always_false_without_a_deadline(): void
    {
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1', null, null, null, null, null);

        $this->assertFalse($rehearsal->hasResponseDeadlinePassed(new DateTimeImmutable('2099-01-01')));
    }

    public function test_mark_and_clear_reminder_sent(): void
    {
        $rehearsal = Rehearsal::create(ProductionId::generate(), 'Act 1', null, null, null, null, null);
        $sentAt = new DateTimeImmutable('2026-09-19 18:00');

        $rehearsal->markReminderSent($sentAt);
        $this->assertSame($sentAt->getTimestamp(), $rehearsal->reminderSentAt()->getTimestamp());

        $rehearsal->clearReminderSentMark();
        $this->assertNull($rehearsal->reminderSentAt());
    }
}
