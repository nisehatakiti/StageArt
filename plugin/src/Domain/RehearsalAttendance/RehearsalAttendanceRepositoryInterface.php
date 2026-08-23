<?php

declare(strict_types=1);

namespace StageArt\Domain\RehearsalAttendance;

use DateTimeImmutable;
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
     * owning Rehearsal has startDateTime >= $from and whose Rehearsal
     * status is not one of $excludedRehearsalStatuses. Joins to the
     * Rehearsal table only to filter/sort/limit - it deliberately does
     * NOT filter by RehearsalAttendancePhase (that is a Domain judgment
     * call - see RehearsalAttendancePhase::forRehearsalStatus() - and
     * stays in the Application layer, not here). Callers must expect
     * both a SCHEDULE_ADJUSTMENT and an ATTENDANCE_CONFIRMATION record
     * for the same Rehearsal to both come back, and de-duplicate
     * themselves.
     *
     * @param string[] $excludedRehearsalStatuses
     * @return RehearsalAttendance[]
     */
    public function findUpcomingByPersonId(
        PersonId $personId,
        DateTimeImmutable $from,
        array $excludedRehearsalStatuses,
        int $limit
    ): array;
}
