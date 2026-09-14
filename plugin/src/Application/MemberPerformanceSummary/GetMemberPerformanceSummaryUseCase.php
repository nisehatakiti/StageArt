<?php

declare(strict_types=1);

namespace StageArt\Application\MemberPerformanceSummary;

use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Settlement\ProductionSettlementCalculator;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationStatus;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendance;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceStatus;

/**
 * Phase 5 (Production運営UI §9): a pure read-only report over data that
 * already exists in full - no new Domain concept, mirroring
 * `GetProductionAccountingSummaryUseCase`'s own aggregate-Read-Model
 * precedent. Rehearsal attendance counts come from
 * `RehearsalAttendanceRepositoryInterface::findByRehearsalIdAndPhase()`
 * (ATTENDANCE_CONFIRMATION phase only - the day-of actual result, not
 * the earlier schedule-adjustment response); ticket sales/attendance
 * counts reuse `ProductionSettlementCalculator::countedReservationsFor()`
 * directly rather than re-deriving the same CHECKED_IN+NO_SHOW selection
 * logic a second time.
 *
 * `ticketSalesCount` (販売実績 = CHECKED_IN + NO_SHOW) and
 * `ticketAttendanceCount` (実来場者数 = CHECKED_IN only) are kept as two
 * separate counts per this Phase's explicit instruction to preserve that
 * distinction - see CheckInConsistencyPolicy.md's own NO_SHOW rule.
 */
final class GetMemberPerformanceSummaryUseCase
{
    private ProductionRepositoryInterface $productions;
    private RehearsalRepositoryInterface $rehearsals;
    private RehearsalAttendanceRepositoryInterface $rehearsalAttendances;
    private ProductionSettlementCalculator $settlementCalculator;
    private MembershipContract $membership;
    private PersonRepositoryInterface $people;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;

    public function __construct(
        ProductionRepositoryInterface $productions,
        RehearsalRepositoryInterface $rehearsals,
        RehearsalAttendanceRepositoryInterface $rehearsalAttendances,
        ProductionSettlementCalculator $settlementCalculator,
        MembershipContract $membership,
        PersonRepositoryInterface $people,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $this->productions = $productions;
        $this->rehearsals = $rehearsals;
        $this->rehearsalAttendances = $rehearsalAttendances;
        $this->settlementCalculator = $settlementCalculator;
        $this->membership = $membership;
        $this->people = $people;
        $this->identity = $identity;
        $this->authorization = $authorization;
    }

    public function execute(GetMemberPerformanceSummaryQuery $query): MemberPerformanceSummaryResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($query->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new MemberPerformanceSummaryAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $productionId = ProductionId::fromString($query->productionId);
        $production = $this->productions->findById($productionId);

        if (! $production) {
            throw new ProductionNotFoundException($query->productionId);
        }

        if (! $this->authorization->canForProduction($requesterId, $productionId, MemberPerformanceSummaryCapability::VIEW)) {
            throw new MemberPerformanceSummaryAccessDeniedException('Only the PrimaryManager can view this Production\'s Member Performance Summary.');
        }

        /** @var array<string, array{attended:int,absent:int,late:int,earlyLeft:int,rehearsalCount:int}> $rehearsalCounts */
        $rehearsalCounts = [];

        foreach ($this->rehearsals->findByProductionId($productionId) as $rehearsal) {
            $records = $this->rehearsalAttendances->findByRehearsalIdAndPhase(
                $rehearsal->id(),
                RehearsalAttendancePhase::attendanceConfirmation()
            );

            foreach ($records as $record) {
                /** @var RehearsalAttendance $record */
                $key = $record->personId()->toString();
                $rehearsalCounts[$key] ??= ['attended' => 0, 'absent' => 0, 'late' => 0, 'earlyLeft' => 0, 'rehearsalCount' => 0];
                $rehearsalCounts[$key]['rehearsalCount']++;

                switch ($record->status()->toString()) {
                    case RehearsalAttendanceStatus::ATTENDED:
                        $rehearsalCounts[$key]['attended']++;
                        break;
                    case RehearsalAttendanceStatus::ABSENT:
                        $rehearsalCounts[$key]['absent']++;
                        break;
                    case RehearsalAttendanceStatus::LATE:
                        $rehearsalCounts[$key]['late']++;
                        break;
                    case RehearsalAttendanceStatus::EARLY_LEFT:
                        $rehearsalCounts[$key]['earlyLeft']++;
                        break;
                }
            }
        }

        /** @var array<string, int> $ticketSalesCounts */
        $ticketSalesCounts = [];
        /** @var array<string, int> $ticketAttendanceCounts */
        $ticketAttendanceCounts = [];

        foreach ($this->settlementCalculator->countedReservationsFor($productionId) as $reservation) {
            /** @var Reservation $reservation */
            $personId = $reservation->attributedPersonId();

            if ($personId === null) {
                continue;
            }

            $key = $personId->toString();
            $ticketSalesCounts[$key] = ($ticketSalesCounts[$key] ?? 0) + 1;

            if ($reservation->status()->equals(ReservationStatus::fromString(ReservationStatus::CHECKED_IN))) {
                $ticketAttendanceCounts[$key] = ($ticketAttendanceCounts[$key] ?? 0) + 1;
            }
        }

        $activeMemberIds = array_map(
            static fn (PersonId $id): string => $id->toString(),
            $this->membership->activeProductionMemberPersonIds($productionId)
        );

        $relevantPersonIds = array_unique(array_merge(
            $activeMemberIds,
            array_keys($rehearsalCounts),
            array_keys($ticketSalesCounts)
        ));

        $members = [];

        foreach ($relevantPersonIds as $personIdString) {
            $rehearsal = $rehearsalCounts[$personIdString] ?? ['attended' => 0, 'absent' => 0, 'late' => 0, 'earlyLeft' => 0, 'rehearsalCount' => 0];
            $person = $this->people->findById(PersonId::fromString($personIdString));
            $displayName = $person !== null && $person->hasName()
                ? "{$person->familyName()} {$person->givenName()}"
                : null;

            $members[] = new MemberPerformanceSummaryLineResult(
                $personIdString,
                $displayName,
                $rehearsal['attended'],
                $rehearsal['absent'],
                $rehearsal['late'],
                $rehearsal['earlyLeft'],
                $rehearsal['rehearsalCount'],
                $ticketSalesCounts[$personIdString] ?? 0,
                $ticketAttendanceCounts[$personIdString] ?? 0
            );
        }

        return new MemberPerformanceSummaryResult($members);
    }
}
