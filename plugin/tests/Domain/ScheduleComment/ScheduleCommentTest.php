<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\ScheduleComment;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\ScheduleComment\ScheduleComment;
use StageArt\Domain\ScheduleComment\ScheduleCommentBody;
use StageArt\Domain\ScheduleComment\ScheduleCommentId;
use StageArt\Domain\TimetableItem\TimetableItemId;

final class ScheduleCommentTest extends TestCase
{
    public function test_create_for_rehearsal(): void
    {
        $author = PersonId::generate();
        $comment = ScheduleComment::createForRehearsal(RehearsalId::generate(), $author, new ScheduleCommentBody('Let\'s move to Studio B.'));

        $this->assertSame('Let\'s move to Studio B.', $comment->body()->toString());
        $this->assertTrue($comment->isAuthoredBy($author));
        $this->assertFalse($comment->isAuthoredBy(PersonId::generate()));
        $this->assertTrue($comment->isForRehearsal());
        $this->assertFalse($comment->isForTimetableItem());
        $this->assertNotNull($comment->rehearsalId());
        $this->assertNull($comment->timetableItemId());
    }

    public function test_create_for_timetable_item(): void
    {
        $author = PersonId::generate();
        $comment = ScheduleComment::createForTimetableItem(TimetableItemId::generate(), $author, new ScheduleCommentBody('15分前集合'));

        $this->assertTrue($comment->isForTimetableItem());
        $this->assertFalse($comment->isForRehearsal());
        $this->assertNotNull($comment->timetableItemId());
        $this->assertNull($comment->rehearsalId());
    }

    public function test_edit_body(): void
    {
        $comment = ScheduleComment::createForRehearsal(RehearsalId::generate(), PersonId::generate(), new ScheduleCommentBody('Original'));

        $comment->editBody(new ScheduleCommentBody('Edited'));

        $this->assertSame('Edited', $comment->body()->toString());
    }

    public function test_body_rejects_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScheduleCommentBody('   ');
    }

    public function test_body_rejects_too_long_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScheduleCommentBody(str_repeat('a', 501));
    }

    public function test_reconstitute_rejects_both_targets_null(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScheduleComment::reconstitute(
            ScheduleCommentId::generate(),
            null,
            null,
            PersonId::generate(),
            new ScheduleCommentBody('invalid'),
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );
    }

    public function test_reconstitute_rejects_both_targets_set(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScheduleComment::reconstitute(
            ScheduleCommentId::generate(),
            RehearsalId::generate(),
            TimetableItemId::generate(),
            PersonId::generate(),
            new ScheduleCommentBody('invalid'),
            new DateTimeImmutable(),
            new DateTimeImmutable()
        );
    }
}
