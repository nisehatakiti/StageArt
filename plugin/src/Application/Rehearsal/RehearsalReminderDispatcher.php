<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use DateTimeImmutable;
use StageArt\Core\Contract\NotificationContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceStatus;

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
 * Recipients (Notification基盤実装 phase §1, confirmed): every current
 * Phase 1 (SCHEDULE_ADJUSTMENT) Attendance target whose status is
 * UNANSWERED *at the moment this method runs* - re-read from the
 * Repository fresh each call, never cached from Reminder-scheduling
 * time, per "Reminder作成時ではなく、実行される時点で最新の回答状態を確認
 * する". AVAILABLE/UNAVAILABLE are both "already answered" and excluded
 * (Phase 1 has exactly these three values - see
 * RehearsalAttendanceStatus::PHASE_1_VALUES - there is no separate
 * "undecided" value to special-case; this phase's instruction's
 * "「未定」は回答済みとして扱う" note has no distinct Phase 1 status to
 * apply to, so the rule collapses to "only UNANSWERED", which is what
 * this filter already does).
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
            if ($attendance->status()->toString() !== RehearsalAttendanceStatus::UNANSWERED) {
                continue;
            }

            $this->notificationContract->notify($attendance->personId(), 'rehearsal_response_reminder', [
                'rehearsal_id' => $rehearsal->id()->toString(),
                'production_id' => $rehearsal->productionId()->toString(),
                'message' => $message,
            ]);
        }

        $rehearsal->markReminderSent(new DateTimeImmutable());
    }
}
