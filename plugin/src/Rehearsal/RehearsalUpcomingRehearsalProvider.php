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
        $attendances = $this->attendances->findByPersonIdExcludingRehearsalStatuses(
            $personId,
            self::EXCLUDED_REHEARSAL_STATUSES
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

        $eligible = [];

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

            // DashboardPolicy.md: "過去に終了したRehearsalは今後の予定一覧に
            // 表示しない" - a Rehearsal that hasn't ENDED yet counts as
            // upcoming, including one currently in progress (start
            // passed, end not yet). Uses each Rehearsal's own
            // timezone-restored DateTimeImmutable (via
            // RehearsalRepositoryInterface::findByIds()'s hydration) -
            // never a raw string/SQL comparison, which previously
            // compared a naive local wall-clock DB value against a
            // UTC-formatted "now" and produced a wrong result whenever a
            // Rehearsal's own timezone differed from PHP's default.
            if (! self::isNotYetEnded($rehearsal, $now)) {
                continue;
            }

            $eligible[] = $attendance;
        }

        usort(
            $eligible,
            static fn ($a, $b): int =>
                $rehearsalsById[$a->rehearsalId()->toString()]->startDateTime()
                    <=> $rehearsalsById[$b->rehearsalId()->toString()]->startDateTime()
        );

        $eligible = array_slice($eligible, 0, $limit);

        $results = [];

        foreach ($eligible as $attendance) {
            $rehearsal = $rehearsalsById[$attendance->rehearsalId()->toString()];
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

    /**
     * DashboardPolicy.md's "終了していない" test: endDateTime takes
     * priority when present (a Rehearsal in progress - start passed, end
     * not yet - still counts as upcoming); falls back to startDateTime
     * when endDateTime is absent. A Rehearsal with neither date set has
     * nothing to judge "not yet ended" against, so it is excluded -
     * matching the prior behavior (a SQL `NULL >= x` comparison was
     * always false), not a newly invented rule.
     */
    private static function isNotYetEnded(Rehearsal $rehearsal, DateTimeImmutable $now): bool
    {
        if ($rehearsal->endDateTime() !== null) {
            return $rehearsal->endDateTime() >= $now;
        }

        if ($rehearsal->startDateTime() !== null) {
            return $rehearsal->startDateTime() >= $now;
        }

        return false;
    }
}
