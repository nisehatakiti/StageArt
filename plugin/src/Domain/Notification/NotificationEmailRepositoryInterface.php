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

    /**
     * StageArt メール招待によるProductionParticipant追加機能: only rows
     * with `verified = true` are ever returned - an unverified
     * NotificationEmail (e.g. Google gave email_verified=false) must
     * never be treated as confirming who owns that address, so it must
     * never be usable to find an existing Person by email (see
     * FindPersonByEmailUseCase). Returns an array, not a single nullable
     * result, because this table has no uniqueness constraint on `email`
     * itself (only on `person_id`) - two different Persons could in
     * principle hold the same verified email, and the caller must treat
     * that as an ambiguous result rather than silently picking one.
     *
     * @return NotificationEmail[]
     */
    public function findVerifiedByEmail(string $email): array;
}
