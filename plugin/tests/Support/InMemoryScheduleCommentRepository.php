<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\ScheduleComment\ScheduleComment;
use StageArt\Domain\ScheduleComment\ScheduleCommentId;
use StageArt\Domain\ScheduleComment\ScheduleCommentRepositoryInterface;
use StageArt\Domain\TimetableItem\TimetableItemId;

final class InMemoryScheduleCommentRepository implements ScheduleCommentRepositoryInterface
{
    /** @var array<string, ScheduleComment> */
    private array $comments = [];

    public function save(ScheduleComment $comment): void
    {
        $this->comments[$comment->id()->toString()] = $comment;
    }

    public function findById(ScheduleCommentId $id): ?ScheduleComment
    {
        return $this->comments[$id->toString()] ?? null;
    }

    public function findByRehearsalId(RehearsalId $rehearsalId): array
    {
        return array_values(array_filter(
            $this->comments,
            static fn (ScheduleComment $comment): bool => $comment->rehearsalId() !== null
                && $comment->rehearsalId()->equals($rehearsalId)
        ));
    }

    public function findByTimetableItemId(TimetableItemId $timetableItemId): array
    {
        return array_values(array_filter(
            $this->comments,
            static fn (ScheduleComment $comment): bool => $comment->timetableItemId() !== null
                && $comment->timetableItemId()->equals($timetableItemId)
        ));
    }

    public function delete(ScheduleCommentId $id): void
    {
        unset($this->comments[$id->toString()]);
    }
}
