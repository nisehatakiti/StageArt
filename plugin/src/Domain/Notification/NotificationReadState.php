<?php

declare(strict_types=1);

namespace StageArt\Domain\Notification;

use DateTimeImmutable;
use StageArt\Domain\Person\PersonId;

/**
 * NotificationPolicy.md (Phase 7.0): "StageArt内通知は以下の状態を管理する。
 * 未読 / 既読". The existing Notification Fact model (see
 * TimetableVersionPublishedNotification's own docblock) deliberately
 * does not fan out a per-recipient row at creation time - audience is
 * resolved dynamically from Production membership at read time. Read
 * state follows the same lazy-row philosophy PushPreference already
 * established: absence of a row means "unread" (the default), and a
 * row is only ever created the first time a specific Person actually
 * reads a specific notification. `notificationId` is stored as an
 * opaque string (the Fact's own id) rather than a typed reference to
 * TimetableVersionPublishedNotificationId specifically, so this same
 * Entity can track read state for future Notification Fact types
 * (Blueprint's `type` discriminator already anticipates more) without
 * a schema change.
 */
final class NotificationReadState
{
    private NotificationReadStateId $id;
    private PersonId $personId;
    private string $notificationId;
    private DateTimeImmutable $readAt;

    private function __construct(
        NotificationReadStateId $id,
        PersonId $personId,
        string $notificationId,
        DateTimeImmutable $readAt
    ) {
        $this->id = $id;
        $this->personId = $personId;
        $this->notificationId = $notificationId;
        $this->readAt = $readAt;
    }

    public static function create(PersonId $personId, string $notificationId): self
    {
        return new self(NotificationReadStateId::generate(), $personId, $notificationId, new DateTimeImmutable());
    }

    public static function reconstitute(
        NotificationReadStateId $id,
        PersonId $personId,
        string $notificationId,
        DateTimeImmutable $readAt
    ): self {
        return new self($id, $personId, $notificationId, $readAt);
    }

    public function id(): NotificationReadStateId
    {
        return $this->id;
    }

    public function personId(): PersonId
    {
        return $this->personId;
    }

    public function notificationId(): string
    {
        return $this->notificationId;
    }

    public function readAt(): DateTimeImmutable
    {
        return $this->readAt;
    }
}
