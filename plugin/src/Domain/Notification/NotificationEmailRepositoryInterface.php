<?php

declare(strict_types=1);

namespace StageArt\Domain\Notification;

use StageArt\Domain\Person\PersonId;

interface NotificationEmailRepositoryInterface
{
    public function save(NotificationEmail $notificationEmail): void;

    /**
     * Returns null when the Person has no StageArt-managed notification
     * email yet (the common case for anyone who has never signed in via
     * Google, and for a Google user Google gave no verified email at
     * sign-in time) - callers must fall back to the lower-priority
     * sources `PersonEmailResolver` already checks, exactly as when a
     * `PushPreference` row is absent.
     */
    public function findByPersonId(PersonId $personId): ?NotificationEmail;
}
