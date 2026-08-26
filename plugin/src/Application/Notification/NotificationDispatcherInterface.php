<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Domain\Person\PersonId;

/**
 * StageArt Core/Module Architecture Phase 2: the Port
 * `Core\Adapter\CoreNotificationAdapter` delegates to for actual
 * delivery, mirroring the existing `AuthMailerInterface`/
 * `WordPressAuthMailer` pattern this codebase already uses for
 * auth-flow email delivery. Deliberately does not model *how* delivery
 * happens (push/email/in-app) - see `WordPressNotificationDispatcher`
 * for the concrete WordPress-hosted implementation, and
 * `docs/architecture/CoreModuleArchitecture.md` for why this exists as
 * its own small Port rather than living inline in the Adapter (keeps
 * `CoreNotificationAdapter` itself free of any WordPress function call,
 * consistent with every other `Core\Adapter\*` class, all of which stay
 * testable under plain PHPUnit with no WordPress bootstrap).
 */
interface NotificationDispatcherInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(PersonId $personId, string $type, array $payload): void;
}
