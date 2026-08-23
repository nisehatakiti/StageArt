<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Notification\PushPreference;
use StageArt\Domain\Notification\PushPreferenceRepositoryInterface;
use StageArt\Domain\Person\PersonId;

final class InMemoryPushPreferenceRepository implements PushPreferenceRepositoryInterface
{
    /** @var array<string, PushPreference> */
    private array $preferences = [];

    public function save(PushPreference $preference): void
    {
        $this->preferences[$preference->id()->toString()] = $preference;
    }

    public function findByPersonId(PersonId $personId): ?PushPreference
    {
        foreach ($this->preferences as $preference) {
            if ($preference->personId()->equals($personId)) {
                return $preference;
            }
        }

        return null;
    }
}
