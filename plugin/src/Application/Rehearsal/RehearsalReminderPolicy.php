<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use DateTimeImmutable;

/**
 * Phase 7 (Rehearsal仕様整合): pure decision logic for the confirmed
 * "回答期限変更時" rules - extracted from any Repository/WordPress/
 * Notification dependency so it is trivially unit-testable and so the
 * two call sites that need it (Create/UpdateRehearsalUseCase) share one
 * implementation rather than each re-deriving the same rule.
 *
 * Confirmed rules this encodes:
 * - Reminder time = deadline − 24h.
 * - Extending an existing deadline later never touches an already-
 *   scheduled/sent Reminder ("期限を後ろへ変更した場合、Reminderの
 *   変更なし").
 * - Pulling an existing deadline earlier reschedules the Reminder to the
 *   new time; if that new time has already passed, send it immediately
 *   instead of scheduling it into the past.
 * - Setting a deadline for the first time (no prior deadline) follows
 *   the same "schedule, or send now if already overdue" rule as a pull-
 *   earlier change.
 * - Clearing a deadline that was previously set cancels any pending
 *   Reminder.
 */
final class RehearsalReminderPolicy
{
    public const ACTION_NONE = 'NONE';
    public const ACTION_SCHEDULE = 'SCHEDULE';
    public const ACTION_SEND_NOW = 'SEND_NOW';
    public const ACTION_CANCEL = 'CANCEL';

    public static function computeReminderAt(DateTimeImmutable $deadline): DateTimeImmutable
    {
        return $deadline->modify('-24 hours');
    }

    /**
     * @return array{action: string, reminderAt: ?DateTimeImmutable}
     */
    public static function decideOnDeadlineChange(
        ?DateTimeImmutable $oldDeadline,
        ?DateTimeImmutable $newDeadline,
        DateTimeImmutable $now
    ): array {
        if ($newDeadline === null) {
            return $oldDeadline === null
                ? ['action' => self::ACTION_NONE, 'reminderAt' => null]
                : ['action' => self::ACTION_CANCEL, 'reminderAt' => null];
        }

        if ($oldDeadline !== null && $newDeadline->getTimestamp() === $oldDeadline->getTimestamp()) {
            return ['action' => self::ACTION_NONE, 'reminderAt' => null];
        }

        if ($oldDeadline !== null && $newDeadline > $oldDeadline) {
            return ['action' => self::ACTION_NONE, 'reminderAt' => null];
        }

        $reminderAt = self::computeReminderAt($newDeadline);

        return $reminderAt <= $now
            ? ['action' => self::ACTION_SEND_NOW, 'reminderAt' => $reminderAt]
            : ['action' => self::ACTION_SCHEDULE, 'reminderAt' => $reminderAt];
    }
}
