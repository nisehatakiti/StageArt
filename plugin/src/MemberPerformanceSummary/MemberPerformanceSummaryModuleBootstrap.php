<?php

declare(strict_types=1);

namespace StageArt\MemberPerformanceSummary;

use StageArt\Application\MemberPerformanceSummary\GetMemberPerformanceSummaryUseCase;
use StageArt\Application\Settlement\ProductionSettlementCalculator;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Rehearsal\RehearsalRepositoryInterface;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendanceRepositoryInterface;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Ticket\TicketRepositoryInterface;
use StageArt\Presentation\Rest\MemberPerformanceSummaryRestController;

/**
 * StageArt Core/Module Architecture Phase 5: this Module's own wiring.
 * Constructs its own `ProductionSettlementCalculator` instance (stateless,
 * pure Domain-repository reads - see that class's own docblock and
 * `SettlementModuleBootstrap`'s identical precedent for why a fresh
 * instance per consumer is intentional, not a duplication smell).
 */
final class MemberPerformanceSummaryModuleBootstrap
{
    /** @var array<int, object> */
    private array $restControllers;

    public function __construct(
        ProductionRepositoryInterface $productions,
        RehearsalRepositoryInterface $rehearsals,
        RehearsalAttendanceRepositoryInterface $rehearsalAttendances,
        PerformanceRepositoryInterface $performances,
        ReservationRepositoryInterface $reservations,
        TicketRepositoryInterface $tickets,
        MembershipContract $membership,
        PersonRepositoryInterface $people,
        IdentityContract $identity,
        AuthorizationContract $authorization
    ) {
        $settlementCalculator = new ProductionSettlementCalculator($performances, $reservations, $tickets);

        $getSummary = new GetMemberPerformanceSummaryUseCase(
            $productions,
            $rehearsals,
            $rehearsalAttendances,
            $settlementCalculator,
            $membership,
            $people,
            $identity,
            $authorization
        );

        $this->restControllers = [
            new MemberPerformanceSummaryRestController($getSummary),
        ];
    }

    /**
     * @return array<int, object>
     */
    public function restControllers(): array
    {
        return $this->restControllers;
    }
}
