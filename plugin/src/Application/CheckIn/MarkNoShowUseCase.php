<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Reservation\ReservationNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * "販売実績(CHECKED_IN + NO_SHOW)" model member declares a no-show at the
 * door after already collecting payment themselves - reception records
 * it without ever generating a CheckIn Fact (the guest never arrived,
 * per CheckIn.md's own distinction) and without any Journal Entry:
 * TicketRevenueConsistencyPolicy.md's "# No-Show" is explicit that
 * "原則としてTicket Revenueは認識しない". NO_SHOW still counts toward Ticket
 * Back/Quota sales performance (via Reservation Status alone, the same
 * soldCount input ProductionSettlementCalculator already reads) without
 * needing any Accounting side effect here.
 */
final class MarkNoShowUseCase
{
    private ReservationRepositoryInterface $reservations;
    private PerformanceRepositoryInterface $performances;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ReservationRepositoryInterface $reservations,
        PerformanceRepositoryInterface $performances,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        TransactionManagerInterface $transactions
    ) {
        $this->reservations = $reservations;
        $this->performances = $performances;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->transactions = $transactions;
    }

    public function execute(MarkNoShowCommand $command): void
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new CheckInAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $reservation = $this->reservations->findById(ReservationId::fromString($command->reservationId));

        if (! $reservation) {
            throw new ReservationNotFoundException($command->reservationId);
        }

        $performance = $this->performances->findById($reservation->performanceId());

        if (! $performance) {
            throw new PerformanceNotFoundException($reservation->performanceId()->toString());
        }

        if (! $performance->id()->equals(PerformanceId::fromString($command->performanceId))) {
            throw new PerformanceMismatchException();
        }

        if (! $this->authorization->canForProduction($requesterId, $performance->productionId(), CheckInCapability::MANAGE)) {
            throw new CheckInAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the CHECKIN_MANAGER Role can mark a no-show.'
            );
        }

        $this->transactions->run(function () use ($reservation, $requesterId): void {
            $reservation->markNoShow($requesterId);
            $this->reservations->save($reservation);
        });
    }
}
