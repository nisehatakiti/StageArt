<?php

declare(strict_types=1);

namespace StageArt\Settlement;

use StageArt\Application\Settlement\GetProductionSettlementSummaryUseCase;
use StageArt\Application\Settlement\ProductionSettlementCalculator;
use StageArt\Application\Settlement\SettleProductionMemberUseCase;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Person\PersonRepositoryInterface;
use StageArt\Domain\Production\ProductionRepositoryInterface;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Settlement\SettlementRepositoryInterface;
use StageArt\Domain\Ticket\TicketRepositoryInterface;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Presentation\Rest\SettlementRestController;

/**
 * StageArt Core/Module Architecture Phase 4 (Check-in/精算/会計連携): the
 * Settlement Module's own wiring. `ProductionSettlementCalculator` is
 * stateless (pure Domain-repository reads, no Settlement-specific state
 * of its own), so `Application\Production\CompleteProductionUseCase`
 * constructs its own separate instance in Plugin::boot() rather than
 * reaching into this Bootstrap for one - see that class's own docblock.
 */
final class SettlementModuleBootstrap
{
    /** @var array<int, object> */
    private array $restControllers;

    public function __construct(
        ProductionRepositoryInterface $productions,
        PerformanceRepositoryInterface $performances,
        ReservationRepositoryInterface $reservations,
        TicketRepositoryInterface $tickets,
        SettlementRepositoryInterface $settlements,
        MembershipContract $membership,
        PersonRepositoryInterface $people,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $calculator = new ProductionSettlementCalculator($performances, $reservations, $tickets);

        $getSummary = new GetProductionSettlementSummaryUseCase(
            $productions,
            $membership,
            $people,
            $settlements,
            $calculator,
            $identity,
            $authorization
        );
        $settleMember = new SettleProductionMemberUseCase(
            $productions,
            $settlements,
            $calculator,
            $identity,
            $authorization,
            $transactions
        );

        $this->restControllers = [
            new SettlementRestController($getSummary, $settleMember),
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
