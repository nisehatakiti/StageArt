<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use StageArt\Core\Contract\NotificationContract;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\Rehearsal;
use Throwable;

/**
 * PR #7 R9: the two notifications docs/20-RehearsalManagementScreen.md
 * defines for the planning stage that had no implementation -
 * §9.1 (a Rehearsal is created as 調整中) and §11 (it is confirmed).
 * Both go through the existing NotificationContract (in-app 個人Home
 * notification and e-mail; there is no Push adapter in the codebase) and
 * carry rehearsal_id / production_id, so the app opens the Rehearsal
 * detail from them like the other Rehearsal notifications.
 *
 * Callers invoke these only after their transaction has committed, so a
 * rolled-back create/confirm is never announced. A failure is contained
 * per recipient (as ExpenseWorkflowNotifier does): it is logged with the
 * notification type and Rehearsal id only, the other recipients are
 * still notified, and the committed operation is reported as done -
 * the notification is an entry point, not the operation itself.
 */
final class RehearsalLifecycleNotifier
{
    public const TYPE_PLANNED = 'rehearsal_planned';
    public const TYPE_CONFIRMED = 'rehearsal_confirmed';

    private NotificationContract $notifications;

    public function __construct(NotificationContract $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * §9.1: every member targeted at creation.
     *
     * @param PersonId[] $targets
     */
    public function planned(Rehearsal $rehearsal, string $productionName, array $targets): void
    {
        if ($rehearsal->startDateTime() === null) {
            return;
        }

        $this->send(
            self::TYPE_PLANNED,
            $rehearsal,
            $targets,
            RehearsalNotificationMessageBuilder::buildPlannedMessage($productionName, $rehearsal->startDateTime())
        );
    }

    /**
     * §11: every member who received a confirmed-attendance record in this
     * confirmation.
     *
     * @param PersonId[] $targets
     */
    public function confirmed(Rehearsal $rehearsal, string $productionName, array $targets): void
    {
        if ($rehearsal->startDateTime() === null) {
            return;
        }

        $this->send(
            self::TYPE_CONFIRMED,
            $rehearsal,
            $targets,
            RehearsalNotificationMessageBuilder::buildConfirmedMessage($productionName, $rehearsal->startDateTime())
        );
    }

    /**
     * @param PersonId[] $targets
     */
    private function send(string $type, Rehearsal $rehearsal, array $targets, string $message): void
    {
        $unique = [];

        foreach ($targets as $personId) {
            $unique[$personId->toString()] = $personId;
        }

        foreach ($unique as $personId) {
            try {
                $this->notifications->notify($personId, $type, [
                    'rehearsal_id' => $rehearsal->id()->toString(),
                    'production_id' => $rehearsal->productionId()->toString(),
                    'message' => $message,
                ]);
            } catch (Throwable $exception) {
                error_log(sprintf(
                    '[StageArt rehearsal notification] %s for rehearsal %s not sent: %s',
                    $type,
                    $rehearsal->id()->toString(),
                    get_class($exception)
                ));
            }
        }
    }
}
