<?php

declare(strict_types=1);

namespace StageArt\Domain\Timetable;

use StageArt\Domain\Rehearsal\RehearsalId;

interface TimetableRepositoryInterface
{
    public function save(Timetable $timetable): void;

    public function findById(TimetableId $id): ?Timetable;

    /**
     * All Versions for a Rehearsal (DRAFT + PUBLISHED + ARCHIVED),
     * ordered by version descending - the Version history list.
     *
     * @return Timetable[]
     */
    public function findByRehearsalId(RehearsalId $rehearsalId): array;

    /**
     * The current official Version for a Rehearsal - at most one row
     * can have PUBLISHED status per Rehearsal at any time (see
     * Timetable.md's "Single Published Version Principle").
     */
    public function findPublishedByRehearsalId(RehearsalId $rehearsalId): ?Timetable;

    /**
     * The single in-progress Version for a Rehearsal, if one exists
     * (see Timetable.md's "Draft" section: at most one DRAFT per
     * Rehearsal at a time).
     */
    public function findDraftByRehearsalId(RehearsalId $rehearsalId): ?Timetable;

    /**
     * Bulk variant of findPublishedByRehearsalId(), added for Print
     * View (Phase 4.5 instruction §26: avoid N+1 queries when a
     * Production has many Rehearsals). One query instead of one per
     * Rehearsal.
     *
     * @param RehearsalId[] $rehearsalIds
     * @return array<string, Timetable> keyed by rehearsalId string; a
     *   Rehearsal with no current Published Version is simply absent
     *   from the returned map (matching findPublishedByRehearsalId()'s
     *   null-when-absent contract, but as a map lookup instead).
     */
    public function findPublishedByRehearsalIds(array $rehearsalIds): array;
}
