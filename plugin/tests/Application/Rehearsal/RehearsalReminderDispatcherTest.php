<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Rehearsal;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\InAppNotificationAdapter;
use StageArt\Application\Rehearsal\RehearsalReminderDispatcher;
use StageArt\Core\Adapter\CoreNotificationAdapter;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendance;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceStatus;
use StageArt\Tests\Support\DirectNotificationDispatcher;
use StageArt\Tests\Support\FakeProductionContextContract;
use StageArt\Tests\Support\InMemoryNotificationRepository;
use StageArt\Tests\Support\InMemoryRehearsalAttendanceRepository;

/**
 * StageArt ホームお知らせ・稽古出欠通知 instruction: end-to-end proof that the
 * Rehearsal a reminder notification is about actually reaches the
 * persisted `Notification` row - exercising the real call path
 * (`RehearsalReminderDispatcher` -> `NotificationContract` ->
 * `InAppNotificationAdapter` -> `NotificationRepositoryInterface`), not
 * just each layer in isolation. `RehearsalReminderDispatcher` itself
 * already put `rehearsal_id` in the payload before this round; this
 * test's job is to prove it now survives all the way to the row the
 * Frontend reads back.
 */
final class RehearsalReminderDispatcherTest extends TestCase
{
    public function test_reminder_notification_persists_the_target_rehearsal_id(): void
    {
        $attendances = new InMemoryRehearsalAttendanceRepository();
        $productionContext = new FakeProductionContextContract();
        $notifications = new InMemoryNotificationRepository();
        $notificationContract = new CoreNotificationAdapter(
            new DirectNotificationDispatcher(new InAppNotificationAdapter($notifications))
        );

        $productionId = ProductionId::generate();
        $productionContext->register($productionId, '踊れチュパカブラ');

        $rehearsal = Rehearsal::create(
            $productionId,
            null,
            null,
            new DateTimeImmutable('+3 days'),
            new DateTimeImmutable('+3 days +2 hours'),
            'Asia/Tokyo',
            null
        );

        $personId = PersonId::generate();
        $attendances->save(RehearsalAttendance::createPhase1($rehearsal->id(), $personId));

        $dispatcher = new RehearsalReminderDispatcher($attendances, $productionContext, $notificationContract);
        $dispatcher->dispatch($rehearsal);

        $rows = $notifications->findByPersonId($personId);
        $this->assertCount(1, $rows);
        $this->assertSame('rehearsal_response_reminder', $rows[0]->type());
        $this->assertSame($rehearsal->id()->toString(), $rows[0]->rehearsalId());
        $this->assertSame($productionId->toString(), $rows[0]->productionId()?->toString());
    }

    public function test_reminder_is_not_sent_to_an_already_answered_attendee(): void
    {
        $attendances = new InMemoryRehearsalAttendanceRepository();
        $productionContext = new FakeProductionContextContract();
        $notifications = new InMemoryNotificationRepository();
        $notificationContract = new CoreNotificationAdapter(
            new DirectNotificationDispatcher(new InAppNotificationAdapter($notifications))
        );

        $productionId = ProductionId::generate();
        $productionContext->register($productionId, '踊れチュパカブラ');

        $rehearsal = Rehearsal::create(
            $productionId,
            null,
            null,
            new DateTimeImmutable('+3 days'),
            new DateTimeImmutable('+3 days +2 hours'),
            'Asia/Tokyo',
            null
        );

        $answeredAttendance = RehearsalAttendance::createPhase1($rehearsal->id(), PersonId::generate());
        $answeredAttendance->respondScheduleAdjustment(RehearsalAttendanceStatus::fromString('AVAILABLE'));
        $attendances->save($answeredAttendance);

        $dispatcher = new RehearsalReminderDispatcher($attendances, $productionContext, $notificationContract);
        $dispatcher->dispatch($rehearsal);

        $rows = $notifications->findByPersonId($answeredAttendance->personId());
        $this->assertCount(0, $rows);
    }
}
