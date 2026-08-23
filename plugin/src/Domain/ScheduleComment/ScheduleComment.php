<?php

declare(strict_types=1);

namespace StageArt\Domain\ScheduleComment;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\TimetableItem\TimetableItemId;

/**
 * ScheduleComment.md's Concept describes comments attached to either a
 * Rehearsal or a Timetable Item. Phase 2 implemented the Rehearsal path
 * only (Timetable Item did not exist yet) with a single required
 * rehearsalId field; Phase 3's review fix adds the Timetable Item path
 * as a second, mutually-exclusive nullable target rather than
 * generalizing to a targetType/targetId pair - this keeps every
 * existing Rehearsal-path call site (rehearsalId(): now ?RehearsalId,
 * but always non-null on a Rehearsal-created comment) working exactly
 * as before, minimizing risk to already-tested behavior. Exactly one of
 * rehearsalId/timetableItemId must be set; the constructor enforces
 * this invariant so a ScheduleComment can never exist with zero or two
 * targets.
 */
final class ScheduleComment
{
    private ScheduleCommentId $id;
    private ?RehearsalId $rehearsalId;
    private ?TimetableItemId $timetableItemId;
    private PersonId $authorPersonId;
    private ScheduleCommentBody $body;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        ScheduleCommentId $id,
        ?RehearsalId $rehearsalId,
        ?TimetableItemId $timetableItemId,
        PersonId $authorPersonId,
        ScheduleCommentBody $body,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ) {
        if (($rehearsalId === null) === ($timetableItemId === null)) {
            throw new InvalidArgumentException(
                'ScheduleComment must have exactly one target: either a Rehearsal or a TimetableItem.'
            );
        }

        $this->id = $id;
        $this->rehearsalId = $rehearsalId;
        $this->timetableItemId = $timetableItemId;
        $this->authorPersonId = $authorPersonId;
        $this->body = $body;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function createForRehearsal(RehearsalId $rehearsalId, PersonId $authorPersonId, ScheduleCommentBody $body): self
    {
        $now = new DateTimeImmutable();

        return new self(ScheduleCommentId::generate(), $rehearsalId, null, $authorPersonId, $body, $now, $now);
    }

    public static function createForTimetableItem(
        TimetableItemId $timetableItemId,
        PersonId $authorPersonId,
        ScheduleCommentBody $body
    ): self {
        $now = new DateTimeImmutable();

        return new self(ScheduleCommentId::generate(), null, $timetableItemId, $authorPersonId, $body, $now, $now);
    }

    public static function reconstitute(
        ScheduleCommentId $id,
        ?RehearsalId $rehearsalId,
        ?TimetableItemId $timetableItemId,
        PersonId $authorPersonId,
        ScheduleCommentBody $body,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt
    ): self {
        return new self($id, $rehearsalId, $timetableItemId, $authorPersonId, $body, $createdAt, $updatedAt);
    }

    /**
     * ScheduleComment.md's "Edit and Delete": the author may edit their own
     * comment. Authorization (who is calling) is enforced at the Application
     * layer, not here - this Entity only knows how to change its own body.
     */
    public function editBody(ScheduleCommentBody $body): void
    {
        $this->body = $body;
        $this->touch();
    }

    public function isAuthoredBy(PersonId $personId): bool
    {
        return $this->authorPersonId->equals($personId);
    }

    public function isForRehearsal(): bool
    {
        return $this->rehearsalId !== null;
    }

    public function isForTimetableItem(): bool
    {
        return $this->timetableItemId !== null;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): ScheduleCommentId
    {
        return $this->id;
    }

    public function rehearsalId(): ?RehearsalId
    {
        return $this->rehearsalId;
    }

    public function timetableItemId(): ?TimetableItemId
    {
        return $this->timetableItemId;
    }

    public function authorPersonId(): PersonId
    {
        return $this->authorPersonId;
    }

    public function body(): ScheduleCommentBody
    {
        return $this->body;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
