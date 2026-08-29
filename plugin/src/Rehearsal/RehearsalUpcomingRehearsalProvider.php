<?php

declare(strict_types=1);

namespace StageArt\Rehearsal;

use DateTimeImmutable;
use StageArt\Application\Dashboard\UpcomingRehearsalProviderInterface;
use StageArt\Application\Dashboard\UpcomingRehearsalResult;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalStatus;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;

/**
 * StageArt Core/Module Architecture Phase 4 §1: the Rehearsal Module's
 * implementation of Core's `UpcomingRehearsalProviderInterface` Port -
 * the inverse-direction counterpart to `RehearsalModuleBootstrap`
 * (which consumes Core Contracts; this class is *consumed by* Core).
 * Holds the exact logic `Application\Dashboard\GetMyDashboardUseCase`
 * used to contain directly (moved here verbatim, not reimplemented) -
 * this is where that logic belongs, since it needs full access to the
 * `Rehearsal`/`RehearsalAttendance` Domain Entities Core must never
 * import.
 *
 * Uses `ProductionContextContract::getProductions()` (bulk) rather than
 * `ProductionRepositoryInterface` directly - the Rehearsal Module still
 * resolves Production data through its own existing Core Contract, not
 * through a second, redundant path.
 */
final class RehearsalUpcomingRehearsalProvider implements UpcomingRehearsalProviderInterface
{
    /** Rehearsal.md's terminal statuses - a completed/cancelled Rehearsal is never "今後の予定". */
    private const EXCLUDED_REHEARSAL_STATUSES = [RehearsalStatus::COMPLETED, RehearsalStatus::CANCELLED];

    private RehearsalRepositoryInterface $rehearsals;
    private RehearsalAttendanceRepositoryInterface $attendances;
    private ProductionContextContract $productionContext;

    public function __construct(
        RehearsalRepositoryInterface $rehearsals,
        RehearsalAttendanceRepositoryInterface $attendances,
        ProductionContextContract $productionContext
    ) {
        $this->rehearsals = $rehearsals;
        $this->attendances = $attendances;
        $this->productionContext = $productionContext;
    }

    public function findUpcomingRehearsalsForPerson(PersonId $personId, DateTimeImmutable $now, int $limit): array
    {
        $attendances = $this->attendances->findUpcomingByPersonId(
            $personId,
            $now,
            self::EXCLUDED_REHEARSAL_STATUSES,
            $limit
        );

        if ($attendances === []) {
            return [];
        }

        $rehearsals = $this->rehearsals->findByIds(array_map(
            static fn ($attendance) => $attendance->rehearsalId(),
            $attendances
        ));

        /** @var array<string, Rehearsal> $rehearsalsById */
        $rehearsalsById = [];
        foreach ($rehearsals as $rehearsal) {
            $rehearsalsById[$rehearsal->id()->toString()] = $rehearsal;
        }

        $productionsById = $this->productionContext->getProductions(array_map(
            static fn (Rehearsal $rehearsal) => $rehearsal->productionId(),
            $rehearsals
        ));

        $results = [];

        foreach ($attendances as $attendance) {
            $rehearsal = $rehearsalsById[$attendance->rehearsalId()->toString()] ?? null;

            if (! $rehearsal) {
                continue;
            }

            // A Rehearsal keeps its superseded Phase 1 record after
            // reaching CONFIRMED (see RehearsalAttendancePhase::
            // forRehearsalStatus()'s docblock) - only the phase matching
            // the Rehearsal's current status counts as "current".
            if (! $attendance->phase()->equals(RehearsalAttendancePhase::forRehearsalStatus($rehearsal->status()))) {
                continue;
            }

            $production = $productionsById[$rehearsal->productionId()->toString()] ?? null;

            if (! $production) {
                continue;
            }

            $results[] = UpcomingRehearsalResult::create(
                $rehearsal->id()->toString(),
                $production->id->toString(),
                $production->name,
                $rehearsal->title(),
                $rehearsal->startDateTime() !== null ? $rehearsal->startDateTime()->format(DATE_ATOM) : null,
                $rehearsal->endDateTime() !== null ? $rehearsal->endDateTime()->format(DATE_ATOM) : null,
                $rehearsal->location(),
                $attendance->status()->toString()
            );
        }

        return $results;
    }
}
