<?php

declare(strict_types=1);

namespace StageArt\Domain\Timetable;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\RehearsalId;

/**
 * Timetable.md: "Timetableの基本的な親DomainはRehearsalである" -
 * Timetable belongs to a Rehearsal, not directly to a Production (see
 * Phase 3's disclosed reading). Phase 3.5 adds Versioning on top of that
 * same Cardinality: a Rehearsal now has zero or more Timetable rows,
 * each one a distinct Version, rather than at most one Timetable row.
 *
 * "No Direct Overwrite Principle" (Timetable.md): once a Version is
 * PUBLISHED, its Business Fact (every field below except status/
 * updatedBy/updatedAt) never changes again. publish() is only reachable
 * from DRAFT, and archive() is only reachable from PUBLISHED - there is
 * no path back from ARCHIVED or forward from PUBLISHED to a mutated
 * PUBLISHED. Changing a published schedule always means creating a new
 * DRAFT Version (see CreateNewTimetableVersionUseCase), never mutating
 * this row.
 *
 * publishedBy/publishedAt are intentionally separate from the generic
 * updatedBy/updatedAt audit pair: archive() also calls touch() (it is a
 * real mutation - the status changes), which would otherwise overwrite
 * "who/when this Version was published" with "who/when a later Version
 * caused this one to be archived", destroying the historical record.
 * publishedBy/publishedAt are set exactly once, at publish() time, and
 * never touched again.
 */
final class Timetable
{
    private TimetableId $id;
    private RehearsalId $rehearsalId;
    private int $version;
    private TimetableStatus $status;
    private ?string $changeSummary;
    private PersonId $createdBy;
    private DateTimeImmutable $createdAt;
    private PersonId $updatedBy;
    private DateTimeImmutable $updatedAt;
    private ?PersonId $publishedBy;
    private ?DateTimeImmutable $publishedAt;

    private function __construct(
        TimetableId $id,
        RehearsalId $rehearsalId,
        int $version,
        TimetableStatus $status,
        ?string $changeSummary,
        PersonId $createdBy,
        DateTimeImmutable $createdAt,
        PersonId $updatedBy,
        DateTimeImmutable $updatedAt,
        ?PersonId $publishedBy,
        ?DateTimeImmutable $publishedAt
    ) {
        $this->id = $id;
        $this->rehearsalId = $rehearsalId;
        $this->version = $version;
        $this->status = $status;
        $this->changeSummary = $changeSummary;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = $updatedAt;
        $this->publishedBy = $publishedBy;
        $this->publishedAt = $publishedAt;
    }

    /**
     * @param int $version 1 for a Rehearsal's first-ever Version; the
     *  Application layer computes this as (max existing version) + 1
     *  (see TimetableVersionNumberResolver).
     */
    public static function create(RehearsalId $rehearsalId, int $version, PersonId $createdBy): self
    {
        if ($version < 1) {
            throw new InvalidArgumentException('Timetable version must be 1 or greater.');
        }

        $now = new DateTimeImmutable();

        return new self(
            TimetableId::generate(),
            $rehearsalId,
            $version,
            TimetableStatus::draft(),
            null,
            $createdBy,
            $now,
            $createdBy,
            $now,
            null,
            null
        );
    }

    public static function reconstitute(
        TimetableId $id,
        RehearsalId $rehearsalId,
        int $version,
        TimetableStatus $status,
        ?string $changeSummary,
        PersonId $createdBy,
        DateTimeImmutable $createdAt,
        PersonId $updatedBy,
        DateTimeImmutable $updatedAt,
        ?PersonId $publishedBy,
        ?DateTimeImmutable $publishedAt
    ): self {
        return new self(
            $id,
            $rehearsalId,
            $version,
            $status,
            $changeSummary,
            $createdBy,
            $createdAt,
            $updatedBy,
            $updatedAt,
            $publishedBy,
            $publishedAt
        );
    }

    /**
     * Only a DRAFT Version can be published. A Version that is already
     * PUBLISHED or ARCHIVED cannot be re-published - Timetable.md's
     * "No Direct Overwrite Principle" means the only way to get a new
     * PUBLISHED Version is to create a fresh DRAFT and publish that.
     */
    public function publish(PersonId $publishedBy, ?string $changeSummary): void
    {
        if (! $this->isDraft()) {
            throw new InvalidArgumentException('Only a DRAFT Timetable Version can be published.');
        }

        $this->status = TimetableStatus::fromString(TimetableStatus::PUBLISHED);
        $this->changeSummary = $changeSummary;
        $this->publishedBy = $publishedBy;
        $this->publishedAt = new DateTimeImmutable();
        $this->touch($publishedBy);
    }

    /**
     * Only a PUBLISHED Version can be archived - this only ever happens
     * as the other half of PublishTimetableVersionUseCase's atomic pair
     * (new Version published -> previous PUBLISHED Version archived).
     */
    public function archive(PersonId $archivedBy): void
    {
        if (! $this->isPublished()) {
            throw new InvalidArgumentException('Only a PUBLISHED Timetable Version can be archived.');
        }

        $this->status = TimetableStatus::fromString(TimetableStatus::ARCHIVED);
        $this->touch($archivedBy);
    }

    public function isDraft(): bool
    {
        return $this->status->equals(TimetableStatus::fromString(TimetableStatus::DRAFT));
    }

    public function isPublished(): bool
    {
        return $this->status->equals(TimetableStatus::fromString(TimetableStatus::PUBLISHED));
    }

    public function isArchived(): bool
    {
        return $this->status->equals(TimetableStatus::fromString(TimetableStatus::ARCHIVED));
    }

    private function touch(PersonId $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): TimetableId
    {
        return $this->id;
    }

    public function rehearsalId(): RehearsalId
    {
        return $this->rehearsalId;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function status(): TimetableStatus
    {
        return $this->status;
    }

    public function changeSummary(): ?string
    {
        return $this->changeSummary;
    }

    public function createdBy(): PersonId
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedBy(): PersonId
    {
        return $this->updatedBy;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function publishedBy(): ?PersonId
    {
        return $this->publishedBy;
    }

    public function publishedAt(): ?DateTimeImmutable
    {
        return $this->publishedAt;
    }
}
