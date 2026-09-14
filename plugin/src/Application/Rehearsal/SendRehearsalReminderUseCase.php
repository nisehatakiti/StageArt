<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalStatus;

/**
 * Phase 7 (Rehearsal仕様整合): the sole consumer of a WordPress Cron
 * "stageart_rehearsal_reminder" firing (see
 * `Infrastructure\WordPress\Rehearsal\WordPressRehearsalReminderScheduler`).
 * No Identity/Authorization here deliberately - unlike every other
 * UseCase in this codebase, this one is never invoked by an
 * authenticated end user, only by the system's own time-triggered
 * callback, so there is no WordPress user to resolve a Person from.
 *
 * Every guard below is a no-op skip, not an exception: a Cron callback
 * firing for a Rehearsal that was cancelled, completed, had its
 * deadline cleared, or already got its Reminder sent (the duplicate-send
 * guard - `Rehearsal::reminderSentAt()`) since it was scheduled is an
 * entirely expected, harmless race, not an error condition to surface
 * anywhere.
 */
final class SendRehearsalReminderUseCase
{
    private const SKIP_STATUSES = [
        RehearsalStatus::CANCELLED,
        RehearsalStatus::COMPLETED,
    ];

    private RehearsalRepositoryInterface $rehearsals;
    private RehearsalReminderDispatcher $dispatcher;
    private TransactionManagerInterface $transactions;

    public function __construct(
        RehearsalRepositoryInterface $rehearsals,
        RehearsalReminderDispatcher $dispatcher,
        TransactionManagerInterface $transactions
    ) {
        $this->rehearsals = $rehearsals;
        $this->dispatcher = $dispatcher;
        $this->transactions = $transactions;
    }

    public function execute(SendRehearsalReminderCommand $command): void
    {
        $rehearsal = $this->rehearsals->findById(RehearsalId::fromString($command->rehearsalId));

        if ($rehearsal === null) {
            return;
        }

        if ($rehearsal->responseDeadline() === null) {
            return;
        }

        if ($rehearsal->reminderSentAt() !== null) {
            return;
        }

        if (in_array($rehearsal->status()->toString(), self::SKIP_STATUSES, true)) {
            return;
        }

        $this->transactions->run(function () use ($rehearsal): void {
            $this->dispatcher->dispatch($rehearsal);
            $this->rehearsals->save($rehearsal);
        });
    }
}
