<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendance;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;

/**
 * The single Application-layer Transaction Boundary for the whole
 * SCHEDULED -> CONFIRMED + Phase 2 generation operation (Phase 2
 * instruction §9): status check, transition, target-member fetch, and
 * bulk Phase 2 RehearsalAttendance creation all happen inside one
 * TransactionManagerInterface::run() call, so a failure partway through
 * (e.g. one Attendance save failing) rolls back the Rehearsal's status
 * change too - a CONFIRMED Rehearsal with incomplete Phase 2 records can
 * never be observed.
 *
 * Idempotency against double-generation is layered: Rehearsal::confirm()
 * itself throws unless the Rehearsal is currently SCHEDULED, so a second
 * call (once already CONFIRMED) never reaches the generation loop at
 * all. The per-record existence check below is a defensive second layer
 * for the race-condition case (two concurrent confirm requests), backed
 * by the DB-level UNIQUE(rehearsal_id, person_id, phase) constraint as
 * the final guarantee.
 *
 * StageArt Core/Module Architecture: depends on `MembershipContract`,
 * not `ParticipantRepositoryInterface`/the former
 * `ProductionMemberResolver` directly (see CreateRehearsalUseCase's
 * matching docblock).
 */
final class ConfirmRehearsalUseCase
{
    private RehearsalRepositoryInterface $rehearsals;
    private ProductionRepositoryInterface $productions;
    private RehearsalAttendanceRepositoryInterface $attendances;
    private MembershipContract $membership;
    private ProductionAuthorizationService $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        RehearsalRepositoryInterface $rehearsals,
        ProductionRepositoryInterface $productions,
        RehearsalAttendanceRepositoryInterface $attendances,
        MembershipContract $membership,
        ProductionAuthorizationService $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->rehearsals = $rehearsals;
        $this->productions = $productions;
        $this->attendances = $attendances;
        $this->membership = $membership;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(ConfirmRehearsalCommand $command): RehearsalResult
    {
        $requester = $this->authorization->resolveCurrentPerson($command->requestedByWordPressUserId);

        if (! $requester) {
            throw new RehearsalAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $rehearsal = $this->rehearsals->findById(RehearsalId::fromString($command->rehearsalId));

        if (! $rehearsal) {
            throw new RehearsalNotFoundException($command->rehearsalId);
        }

        $production = $this->productions->findById($rehearsal->productionId());

        if (! $production) {
            throw new ProductionNotFoundException($rehearsal->productionId()->toString());
        }

        if (! $this->authorization->hasProductionCapability($requester, $production, RehearsalCapability::MANAGE)) {
            throw new RehearsalAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the REHEARSAL_MANAGER Role can confirm this Rehearsal.'
            );
        }

        $phase = RehearsalAttendancePhase::attendanceConfirmation();

        $rehearsal = $this->transactions->run(
            function () use ($rehearsal, $production, $phase): Rehearsal {
                $rehearsal->confirm();
                $this->rehearsals->save($rehearsal);

                foreach ($this->membership->activeProductionMemberPersonIds($production->id()) as $personId) {
                    $existing = $this->attendances->findByRehearsalIdAndPersonIdAndPhase($rehearsal->id(), $personId, $phase);

                    if ($existing !== null) {
                        continue;
                    }

                    $this->attendances->save(RehearsalAttendance::createPhase2($rehearsal->id(), $personId));
                }

                return $rehearsal;
            }
        );

        return RehearsalResult::fromDomain($rehearsal);
    }
}
