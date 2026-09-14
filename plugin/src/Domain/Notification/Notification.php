<?php

declare(strict_types=1);

namespace StageArt\Domain\Notification;

use DateTimeImmutable;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * The generic, per-recipient In-App Notification Fact this new phase's
 * instruction §3 asks for - deliberately NOT a refactor of
 * `TimetableVersionPublishedNotification` (that Entity's own docblock
 * and this phase's instruction both say not to touch it: it deliberately
 * does not fan out a per-recipient row, resolving Production-membership
 * audience dynamically at read time instead - a real, different design
 * that still works for its one existing caller,
 * `PublishTimetableVersionUseCase`). This Entity is the opposite shape
 * on purpose: one row per (Person, event), because every notification
 * that creates it already knows its exact, specific recipient (Rehearsal
 * Cancel/Reminder target lists, not "every Production member").
 *
 * Read/unread lives directly on this Entity (a plain `readAt` field)
 * rather than through the separate `NotificationReadState` lazy-row
 * table `TimetableVersionPublishedNotification` uses - that table's own
 * "audience isn't stored, so read-state needs its own row per
 * (person, notification)" reason for existing does not apply here: this
 * row already belongs to exactly one Person, so a second table would
 * only add complexity for no benefit.
 */
final class Notification
{
    private NotificationId $id;
    private PersonId $personId;
    private string $type;
    private string $message;
    private ?ProductionId $productionId;
    private ?DateTimeImmutable $readAt;
    private DateTimeImmutable $createdAt;

    private function __construct(
        NotificationId $id,
        PersonId $personId,
        string $type,
        string $message,
        ?ProductionId $productionId,
        ?DateTimeImmutable $readAt,
        DateTimeImmutable $createdAt
    ) {
        $this->id = $id;
        $this->personId = $personId;
        $this->type = $type;
        $this->message = $message;
        $this->productionId = $productionId;
        $this->readAt = $readAt;
        $this->createdAt = $createdAt;
    }

    public static function create(PersonId $personId, string $type, string $message, ?ProductionId $productionId): self
    {
        return new self(NotificationId::generate(), $personId, $type, $message, $productionId, null, new DateTimeImmutable());
    }

    public static function reconstitute(
        NotificationId $id,
        PersonId $personId,
        string $type,
        string $message,
        ?ProductionId $productionId,
        ?DateTimeImmutable $readAt,
        DateTimeImmutable $createdAt
    ): self {
        return new self($id, $personId, $type, $message, $productionId, $readAt, $createdAt);
    }

    /** Idempotent by design, matching `NotificationReadState`'s own
     * "first read wins" semantics - calling this twice leaves the
     * original `readAt` untouched. */
    public function markRead(): void
    {
        if ($this->readAt === null) {
            $this->readAt = new DateTimeImmutable();
        }
    }

    public function isRead(): bool
    {
        return $this->readAt !== null;
    }

    public function id(): NotificationId
    {
        return $this->id;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function productionId(): ?ProductionId
    {
        return $this->productionId;
    }

    public function readAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
