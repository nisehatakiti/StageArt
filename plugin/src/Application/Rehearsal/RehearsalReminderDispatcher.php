<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use DateTimeImmutable;
use StageArt\Core\Contract\NotificationContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;

/**
 * Phase 7 (Rehearsal仕様整合): the actual "send the Reminder now" work,
 * shared by `SendRehearsalReminderUseCase` (the Cron-triggered entry
 * point) and Create/UpdateRehearsalUseCase's own immediate-send branch
 * (§5 ケースB - a newly-set or pulled-earlier deadline whose Reminder
 * time has already passed). Deliberately does not manage its own
 * transaction or call `RehearsalRepositoryInterface::save()` - both
 * callers are already inside their own Transaction Boundary and already
 * hold the Repository, matching `PublishTimetableVersionUseCase`'s
 * established "notify from inside the triggering transaction" pattern
 * (see that class's own docblock) rather than introducing a queue/
 * outbox this codebase has no other example of.
 *
 * Recipients: every current Phase 1 (SCHEDULE_ADJUSTMENT) Attendance
 * target for this Rehearsal - "予定稽古には回答期限を設定する" reads the
 * deadline as governing that phase specifically. Sent to ALL of them
 * regardless of whether they have already answered: no existing rule
 * anywhere in this codebase defines an "already answered, so skip"
 * exclusion for any notification type, and Phase 7's own instruction
 * says not to invent one - see this Phase's report for the disclosed
 * 要判断事項.
 */
final class RehearsalReminderDispatcher
{
    private RehearsalAttendanceRepositoryInterface $attendances;
    private ProductionContextContract $productionContext;
    private NotificationContract $notificationContract;

    public function __construct(
        RehearsalAttendanceRepositoryInterface $attendances,
        ProductionContextContract $productionContext,
        NotificationContract $notificationContract
    ) {
        $this->attendances = $attendances;
        $this->productionContext = $productionContext;
        $this->notificationContract = $notificationContract;
    }

    public function dispatch(Rehearsal $rehearsal): void
    {
        if ($rehearsal->startDateTime() === null) {
            return;
        }

        $production = $this->productionContext->getProduction($rehearsal->productionId());

        if ($production === null) {
            return;
        }

        $message = RehearsalNotificationMessageBuilder::buildReminderMessage($production->name, $rehearsal->startDateTime());

        foreach ($this->attendances->findByRehearsalIdAndPhase($rehearsal->id(), RehearsalAttendancePhase::scheduleAdjustment()) as $attendance) {
            $this->notificationContract->notify($attendance->personId(), 'rehearsal_response_reminder', [
                'rehearsal_id' => $rehearsal->id()->toString(),
                'production_id' => $rehearsal->productionId()->toString(),
                'message' => $message,
            ]);
        }

        $rehearsal->markReminderSent(new DateTimeImmutable());
    }
}
