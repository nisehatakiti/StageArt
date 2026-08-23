<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use DateTimeImmutable;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendance;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceId;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;

final class InMemoryRehearsalAttendanceRepository implements RehearsalAttendanceRepositoryInterface
{
    /** @var array<string, RehearsalAttendance> */
    private array $attendances = [];

    /**
     * findUpcomingByPersonId() simulates the real Repository's SQL JOIN
     * against the rehearsals table - the fake needs the same Rehearsal
     * data the real one would read via a live JOIN, so tests register it
     * here rather than this fake reaching into another fake.
     *
     * @var array<string, Rehearsal>
     */
    private array $rehearsals = [];

    public function registerRehearsal(Rehearsal $rehearsal): void
    {
        $this->rehearsals[$rehearsal->id()->toString()] = $rehearsal;
    }

    public function save(RehearsalAttendance $attendance): void
    {
        foreach ($this->attendances as $existing) {
            if ($existing->id()->equals($attendance->id())) {
                continue;
            }

            if (
                $existing->rehearsalId()->equals($attendance->rehearsalId())
                && $existing->personId()->equals($attendance->personId())
                && $existing->phase()->equals($attendance->phase())
            ) {
                throw new \RuntimeException('Duplicate RehearsalAttendance for RehearsalId+PersonId+Phase.');
            }
        }

        $this->attendances[$attendance->id()->toString()] = $attendance;
    }

    public function findById(RehearsalAttendanceId $id): ?RehearsalAttendance
    {
        return $this->attendances[$id->toString()] ?? null;
    }

    public function findByRehearsalIdAndPhase(RehearsalId $rehearsalId, RehearsalAttendancePhase $phase): array
    {
        return array_values(array_filter(
            $this->attendances,
            static fn (RehearsalAttendance $attendance): bool => $attendance->rehearsalId()->equals($rehearsalId)
                && $attendance->phase()->equals($phase)
        ));
    }

    public function findByRehearsalIdAndPersonIdAndPhase(
        RehearsalId $rehearsalId,
        PersonId $personId,
        RehearsalAttendancePhase $phase
    ): ?RehearsalAttendance {
        foreach ($this->attendances as $attendance) {
            if (
                $attendance->rehearsalId()->equals($rehearsalId)
                && $attendance->personId()->equals($personId)
                && $attendance->phase()->equals($phase)
            ) {
                return $attendance;
            }
        }

        return null;
    }

    public function findUpcomingByPersonId(
        PersonId $personId,
        DateTimeImmutable $from,
        array $excludedRehearsalStatuses,
        int $limit
    ): array {
        $matches = [];

        foreach ($this->attendances as $attendance) {
            if (! $attendance->personId()->equals($personId)) {
                continue;
            }

            $rehearsal = $this->rehearsals[$attendance->rehearsalId()->toString()] ?? null;

            if (! $rehearsal || $rehearsal->startDateTime() === null) {
                continue;
            }

            if ($rehearsal->startDateTime() < $from) {
                continue;
            }

            if (in_array($rehearsal->status()->toString(), $excludedRehearsalStatuses, true)) {
                continue;
            }

            $matches[] = $attendance;
        }

        usort(
            $matches,
            fn (RehearsalAttendance $a, RehearsalAttendance $b): int =>
                $this->rehearsals[$a->rehearsalId()->toString()]->startDateTime()
                    <=> $this->rehearsals[$b->rehearsalId()->toString()]->startDateTime()
        );

        return array_slice($matches, 0, $limit);
    }
}
