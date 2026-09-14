<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Notification;

use StageArt\Application\Notification\NotificationDeliveryAdapterInterface;
use StageArt\Application\Notification\NotificationDispatcherInterface;
use StageArt\Domain\Person\PersonId;
use Throwable;

/**
 * Notification基盤実装 phase: previously fired only a WordPress Action
 * Hook (`stageart_notification`) with zero registered listeners - an
 * event-emission-only stub, not real delivery (see this class's own
 * prior docblock, kept below for the historical record of that decision
 * and why the hook itself stays). Now also runs every registered
 * `NotificationDeliveryAdapterInterface` (In-App today; Email today via
 * `WordPressEmailNotificationAdapter`; Push has no real provider decided
 * yet, so it stays exactly what it was - the `do_action` hook below is
 * that channel's "event emitted, delivery pending a provider" point,
 * per this phase's §4/§24 explicit instruction to disclose rather than
 * fake a Push implementation).
 *
 * §2/§11 (this phase's confirmed rule): a delivery failure must never
 * roll back the business transaction that triggered `notify()` - every
 * caller (`CancelRehearsalUseCase`, `SendRehearsalReminderUseCase`,
 * `PublishTimetableVersionUseCase`, Create/UpdateRehearsalUseCase's
 * immediate-Reminder branch) calls `notify()` from inside its own
 * Transaction Boundary, so this is the one choke point every one of
 * those call sites passes through - catching here, once, protects all
 * of them uniformly rather than requiring each caller to remember to
 * wrap its own `notify()` call. `error_log()` (no new logging
 * dependency) is the only failure trace kept; this phase does not build
 * a retry queue (§2's own explicit permission to keep this minimal) -
 * see this phase's report for the disclosed re-send gap.
 */
final class WordPressNotificationDispatcher implements NotificationDispatcherInterface
{
    /** @var NotificationDeliveryAdapterInterface[] */
    private array $adapters;

    /**
     * @param NotificationDeliveryAdapterInterface[] $adapters
     */
    public function __construct(array $adapters = [])
    {
        $this->adapters = $adapters;
    }

    public function dispatch(PersonId $personId, string $type, array $payload): void
    {
        do_action('stageart_notification', $personId->toString(), $type, $payload);

        foreach ($this->adapters as $adapter) {
            try {
                $adapter->deliver($personId, $type, $payload);
            } catch (Throwable $exception) {
                error_log(sprintf(
                    '[StageArt Notification] %s failed to deliver type=%s to person=%s: %s',
                    get_class($adapter),
                    $type,
                    $personId->toString(),
                    $exception->getMessage()
                ));
            }
        }
    }
}
