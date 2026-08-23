<?php

declare(strict_types=1);

namespace StageArt\Domain\Notification;

use StageArt\Domain\Person\PersonId;

interface PushPreferenceRepositoryInterface
{
    public function save(PushPreference $preference): void;

    /**
     * Returns null when the Person has never set a preference. Callers
     * must treat a null result as "enabled = true" (see PushPreference's
     * docblock) - no row is created just to represent the default.
     */
    public function findByPersonId(PersonId $personId): ?PushPreference;
}
