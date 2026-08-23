<?php

declare(strict_types=1);

namespace StageArt\Domain\ScheduleComment;

use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\TimetableItem\TimetableItemId;

interface ScheduleCommentRepositoryInterface
{
    public function save(ScheduleComment $comment): void;

    public function findById(ScheduleCommentId $id): ?ScheduleComment;

    /**
     * @return ScheduleComment[]
     */
    public function findByRehearsalId(RehearsalId $rehearsalId): array;

    /**
     * @return ScheduleComment[]
     */
    public function findByTimetableItemId(TimetableItemId $timetableItemId): array;

    public function delete(ScheduleCommentId $id): void;
}
