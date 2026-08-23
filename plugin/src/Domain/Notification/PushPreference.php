<?php

declare(strict_types=1);

namespace StageArt\Domain\Notification;

use DateTimeImmutable;
use StageArt\Domain\Person\PersonId;

/**
 * Phase 4 instruction §15/§16 ("Push ON/OFF") and Notification.md's
 * "# Push Preference": a per-Person setting for whether Push delivery
 * is wanted, deliberately separate from Authorization (it never gates
 * whether a Person can read Timetable/Production Schedule/Notification
 * data - see Notification.md's explicit principle). Enabled defaults to
 * true when no row exists yet (see PushPreferenceRepositoryInterface's
 * docblock) - this Entity only exists once a Person has ever been
 * looked up/changed, not proactively created for every Person.
 *
 * Does not model actual Push delivery (device tokens, FCM/APNs) - see
 * Notification.md's "本Phaseで実装しない範囲".
 */
final class PushPreference
{
    private PushPreferenceId $id;
    private PersonId $personId;
    private bool $enabled;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        PushPreferenceId $id,
        PersonId $personId,
        bool $enabled,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->personId = $personId;
        $this->enabled = $enabled;
        $this->updatedAt = $updatedAt;
    }

    public static function create(PersonId $personId, bool $enabled): self
    {
        return new self(PushPreferenceId::generate(), $personId, $enabled, new DateTimeImmutable());
    }

    public static function reconstitute(
        PushPreferenceId $id,
        PersonId $personId,
        bool $enabled,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $personId, $enabled, $updatedAt);
    }

    public function id(): PushPreferenceId
    {
        return $this->id;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        $this->updatedAt = new DateTimeImmutable();
    }
}
