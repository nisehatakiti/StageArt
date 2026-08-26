<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Notification;

use StageArt\Application\Notification\NotificationDispatcherInterface;
use StageArt\Domain\Person\PersonId;

/**
 * Fires a WordPress Action Hook (`stageart_notification`) rather than
 * delivering anything itself - StageArt has no push/email delivery
 * mechanism for general (non-auth) notifications yet (see
 * `StageArt\Domain\Notification\PushPreference`'s own docblock: "Does
 * not model actual Push delivery"). This is a genuine, working WordPress
 * extension point: any future delivery mechanism (a push sender, a
 * digest emailer) can `add_action('stageart_notification', ...)`
 * without this dispatcher - or any Core/Module code calling it - ever
 * needing to change. Firing zero listeners today is the honest current
 * state, not a placeholder pretending to deliver something.
 */
final class WordPressNotificationDispatcher implements NotificationDispatcherInterface
{
    public function dispatch(PersonId $personId, string $type, array $payload): void
    {
        do_action('stageart_notification', $personId->toString(), $type, $payload);
    }
}
