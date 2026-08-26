<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Person\PersonId;

/**
 * StageArt Core/Module Architecture: the common Interface a Domain
 * Module would send a notification through, so notification delivery
 * (push/email/in-app read-state - StageArt Core's existing
 * `stageart_notification_read_states`/`stageart_push_preferences`
 * infrastructure) stays a Core-owned concern rather than each Module
 * reimplementing its own delivery mechanism.
 *
 * Not yet adopted by the Rehearsal Module's existing
 * `TimetableVersionPublishedNotification` (see
 * docs/architecture/CoreModuleArchitecture.md's "Known remaining
 * coupling" - that Notification type currently lives in Core's own
 * `Domain\Notification` namespace even though its meaning is Rehearsal/
 * Timetable-specific, a pre-existing structure this phase does not
 * migrate to avoid a risky rename of a persisted Domain Entity/table).
 * Defined now so new Module notification needs (Ticket, Accounting)
 * have a Contract to build against from the start, rather than
 * repeating that same coupling.
 */
interface NotificationContract
{
    /**
     * @param array<string, mixed> $payload
     */
    public function notify(PersonId $personId, string $type, array $payload): void;
}
