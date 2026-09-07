<?php

declare(strict_types=1);

namespace StageArt\Domain\RehearsalAttendance;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\RehearsalId;

interface RehearsalAttendanceRepositoryInterface
{
    public function save(RehearsalAttendance $attendance): void;

    public function findById(RehearsalAttendanceId $id): ?RehearsalAttendance;

    /**
     * @return RehearsalAttendance[]
     */
    public function findByRehearsalIdAndPhase(RehearsalId $rehearsalId, RehearsalAttendancePhase $phase): array;

    public function findByRehearsalIdAndPersonIdAndPhase(
        RehearsalId $rehearsalId,
        PersonId $personId,
        RehearsalAttendancePhase $phase
    ): ?RehearsalAttendance;

    /**
     * Phase 7.3 (Dashboard Aggregate): every record for a Person whose
     * owning Rehearsal's status is not one of $excludedRehearsalStatuses.
     * Joins to the Rehearsal table only to filter by status - it
     * deliberately does NOT judge "upcoming" here, and NOT sort or limit.
     *
     * "Upcoming" requires comparing each Rehearsal's own timezone-restored
     * startDateTime/endDateTime (a DateTimeImmutable comparison) against
     * "now" - Rehearsal.md's Option C design (start_date_time/
     * end_date_time stored as naive wall-clock values, meaningful only
     * together with a separate timezone column) means that comparison
     * can only be made correctly once a Rehearsal has been hydrated
     * through RehearsalRepositoryInterface (which restores the
     * timezone). This Infrastructure-level method has no access to that
     * hydration and must not re-implement it as a second, naive
     * DB-string comparison (see git history: WordPressRehearsalAttendance
     * Repository previously compared the naive DB column directly
     * against a UTC-formatted "now" string, which was wrong whenever a
     * Rehearsal's own timezone differed from PHP's default). Callers
     * (RehearsalUpcomingRehearsalProvider) must fetch the matching
     * Rehearsal entities, apply the "not yet ended" date judgment and
     * RehearsalAttendancePhase::forRehearsalStatus() phase-matching
     * themselves, then sort/limit using the hydrated DateTimeImmutable
     * values.
     *
     * It also deliberately does NOT filter by RehearsalAttendancePhase
     * (that is a Domain judgment call - see RehearsalAttendancePhase::
     * forRehearsalStatus()) and stays in the Application layer, not
     * here). Callers must expect both a SCHEDULE_ADJUSTMENT and an
     * ATTENDANCE_CONFIRMATION record for the same Rehearsal to both come
     * back, and de-duplicate themselves.
     *
     * @param string[] $excludedRehearsalStatuses
     * @return RehearsalAttendance[]
     */
    public function findByPersonIdExcludingRehearsalStatuses(
        PersonId $personId,
        array $excludedRehearsalStatuses
    ): array;
}
