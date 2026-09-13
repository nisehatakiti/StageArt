<?php

declare(strict_types=1);

namespace StageArt\Reservation;

use StageArt\Application\Reservation\CancelReservationUseCase;
use StageArt\Application\Reservation\CreateReservationUseCase;
use StageArt\Application\Reservation\GetReservationByNumberUseCase;
use StageArt\Application\Reservation\ListReservationsUseCase;
use StageArt\Application\Reservation\UpdateReservationUseCase;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\IssuedTicket\IssuedTicketRepositoryInterface;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Ticket\TicketRepositoryInterface;
use StageArt\Presentation\Rest\ReservationRestController;

/**
 * StageArt Core/Module Architecture Phase 3 Ticket/Reservation基盤
 * (instruction §26): Reservation Module's own wiring, kept separate from
 * TicketModuleBootstrap even though both are introduced in the same
 * Phase - "TicketからReservationのデータを直接所有するような逆方向の設計に
 * はしない" (§26). Reservation reads Ticket/Performance data (the
 * correct, forward direction - a Reservation references a Ticket and
 * belongs to a Performance) via their own Domain Repository interfaces,
 * the same direct-Domain-dependency precedent already used throughout
 * this Phase.
 */
final class ReservationModuleBootstrap
{
    /** @var array<int, object> */
    private array $restControllers;

    public function __construct(
        ReservationRepositoryInterface $reservations,
        IssuedTicketRepositoryInterface $issuedTickets,
        TicketRepositoryInterface $tickets,
        PerformanceRepositoryInterface $performances,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $createReservation = new CreateReservationUseCase(
            $performances,
            $tickets,
            $reservations,
            $issuedTickets,
            $productionContext,
            $identity,
            $transactions
        );
        $getReservationByNumber = new GetReservationByNumberUseCase($reservations);
        $updateReservation = new UpdateReservationUseCase($reservations, $performances, $productionContext);
        $cancelReservation = new CancelReservationUseCase($reservations, $performances);
        $listReservations = new ListReservationsUseCase($reservations, $performances, $productionContext, $identity, $authorization);

        $this->restControllers = [
            new ReservationRestController(
                $createReservation,
                $getReservationByNumber,
                $updateReservation,
                $cancelReservation,
                $listReservations
            ),
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
