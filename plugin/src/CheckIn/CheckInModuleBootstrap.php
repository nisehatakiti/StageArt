<?php

declare(strict_types=1);

namespace StageArt\CheckIn;

use StageArt\Application\CheckIn\ChangeReservationAttributionUseCase;
use StageArt\Application\CheckIn\CheckInByNumberUseCase;
use StageArt\Application\CheckIn\CheckInProcessor;
use StageArt\Application\CheckIn\CheckInReservationUseCase;
use StageArt\Application\CheckIn\CreateWalkUpReservationUseCase;
use StageArt\Application\CheckIn\DecreaseReservationGuestCountUseCase;
use StageArt\Application\CheckIn\MarkNoShowUseCase;
use StageArt\Application\CheckIn\ReverseCheckInUseCase;
use StageArt\Application\CheckIn\SearchReservationsForCheckInUseCase;
use StageArt\Application\CheckIn\StandardAccountResolver;
use StageArt\Application\CheckIn\WalkUpIdempotencyStoreInterface;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\OrganizationContextContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\Account\AccountRepositoryInterface;
use StageArt\Domain\CheckIn\CheckInRepositoryInterface;
use StageArt\Domain\IssuedTicket\IssuedTicketRepositoryInterface;
use StageArt\Domain\JournalEntry\JournalEntryRepositoryInterface;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Ticket\TicketRepositoryInterface;
use StageArt\Presentation\Rest\CheckInRestController;

/**
 * StageArt Core/Module Architecture Phase 4 (Check-in/精算/会計連携): the
 * Check-in Module's own wiring, mirroring ReservationModuleBootstrap/
 * AccountingModuleBootstrap's precedent exactly. `StandardAccountResolver`
 * and `CheckInProcessor` are this Module's own internal collaborators
 * (not Core Contracts, not exposed outside this Bootstrap) shared by
 * every UseCase that needs to perform an actual Check-in.
 */
final class CheckInModuleBootstrap
{
    /** @var array<int, object> */
    private array $restControllers;

    public function __construct(
        ReservationRepositoryInterface $reservations,
        PerformanceRepositoryInterface $performances,
        TicketRepositoryInterface $tickets,
        IssuedTicketRepositoryInterface $issuedTickets,
        CheckInRepositoryInterface $checkIns,
        AccountRepositoryInterface $accounts,
        JournalEntryRepositoryInterface $journalEntries,
        ProductionContextContract $productionContext,
        OrganizationContextContract $organizationContext,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        MembershipContract $membership,
        WalkUpIdempotencyStoreInterface $walkUpIdempotencyStore,
        TransactionManagerInterface $transactions
    ) {
        $standardAccounts = new StandardAccountResolver($accounts);
        $processor = new CheckInProcessor(
            $reservations,
            $checkIns,
            $productionContext,
            $organizationContext,
            $journalEntries,
            $standardAccounts
        );

        $checkInReservation = new CheckInReservationUseCase(
            $reservations,
            $performances,
            $checkIns,
            $identity,
            $authorization,
            $processor,
            $transactions
        );
        $checkInByNumber = new CheckInByNumberUseCase($reservations, $checkInReservation);
        $markNoShow = new MarkNoShowUseCase($reservations, $performances, $identity, $authorization, $transactions);
        $reverseCheckIn = new ReverseCheckInUseCase(
            $reservations,
            $performances,
            $checkIns,
            $identity,
            $authorization,
            $processor,
            $transactions
        );
        $searchReservations = new SearchReservationsForCheckInUseCase($reservations, $performances, $identity, $authorization);
        $createWalkUpReservation = new CreateWalkUpReservationUseCase(
            $performances,
            $tickets,
            $reservations,
            $issuedTickets,
            $checkIns,
            $identity,
            $authorization,
            $membership,
            $processor,
            $walkUpIdempotencyStore,
            $transactions
        );
        $changeAttribution = new ChangeReservationAttributionUseCase($reservations, $performances, $identity, $authorization);
        $decreaseGuestCount = new DecreaseReservationGuestCountUseCase($reservations, $performances, $identity, $authorization, $transactions);

        $this->restControllers = [
            new CheckInRestController(
                $checkInReservation,
                $checkInByNumber,
                $markNoShow,
                $reverseCheckIn,
                $searchReservations,
                $createWalkUpReservation,
                $changeAttribution,
                $decreaseGuestCount
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
