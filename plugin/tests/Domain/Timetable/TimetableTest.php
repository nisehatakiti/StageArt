<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Timetable;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Timetable\Timetable;
use StageArt\Domain\Timetable\TimetableStatus;

final class TimetableTest extends TestCase
{
    public function test_create_starts_draft_at_given_version(): void
    {
        $creator = PersonId::generate();
        $timetable = Timetable::create(RehearsalId::generate(), 1, $creator);

        $this->assertSame(TimetableStatus::DRAFT, $timetable->status()->toString());
        $this->assertSame(1, $timetable->version());
        $this->assertTrue($timetable->createdBy()->equals($creator));
        $this->assertTrue($timetable->updatedBy()->equals($creator));
        $this->assertNull($timetable->publishedBy());
        $this->assertNull($timetable->publishedAt());
    }

    public function test_create_rejects_version_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Timetable::create(RehearsalId::generate(), 0, PersonId::generate());
    }

    public function test_publish_sets_published_by_and_at(): void
    {
        $timetable = Timetable::create(RehearsalId::generate(), 1, PersonId::generate());
        $publisher = PersonId::generate();

        $timetable->publish($publisher, '初回公開');

        $this->assertSame(TimetableStatus::PUBLISHED, $timetable->status()->toString());
        $this->assertTrue($timetable->publishedBy()->equals($publisher));
        $this->assertNotNull($timetable->publishedAt());
        $this->assertSame('初回公開', $timetable->changeSummary());
    }

    public function test_publish_rejects_non_draft(): void
    {
        $timetable = Timetable::create(RehearsalId::generate(), 1, PersonId::generate());
        $timetable->publish(PersonId::generate(), null);

        $this->expectException(InvalidArgumentException::class);
        $timetable->publish(PersonId::generate(), null);
    }

    public function test_archive_only_reachable_from_published(): void
    {
        $timetable = Timetable::create(RehearsalId::generate(), 1, PersonId::generate());

        $this->expectException(InvalidArgumentException::class);
        $timetable->archive(PersonId::generate());
    }

    public function test_archive_does_not_overwrite_published_by_or_at(): void
    {
        $timetable = Timetable::create(RehearsalId::generate(), 1, PersonId::generate());
        $publisher = PersonId::generate();
        $timetable->publish($publisher, null);

        $originalPublishedAt = $timetable->publishedAt();

        $timetable->archive(PersonId::generate());

        $this->assertSame(TimetableStatus::ARCHIVED, $timetable->status()->toString());
        $this->assertTrue($timetable->publishedBy()->equals($publisher), 'archive() must not overwrite the original publisher.');
        $this->assertEquals($originalPublishedAt, $timetable->publishedAt(), 'archive() must not overwrite the original publish timestamp.');
    }

    public function test_isDraft_isPublished_isArchived(): void
    {
        $timetable = Timetable::create(RehearsalId::generate(), 1, PersonId::generate());
        $this->assertTrue($timetable->isDraft());
        $this->assertFalse($timetable->isPublished());
        $this->assertFalse($timetable->isArchived());

        $timetable->publish(PersonId::generate(), null);
        $this->assertFalse($timetable->isDraft());
        $this->assertTrue($timetable->isPublished());
        $this->assertFalse($timetable->isArchived());

        $timetable->archive(PersonId::generate());
        $this->assertFalse($timetable->isDraft());
        $this->assertFalse($timetable->isPublished());
        $this->assertTrue($timetable->isArchived());
    }
}
