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
 * per CheckIn.md's own distinction).
 *
 * StageArt 予約→発券→受付Check-in一連接続 instruction (confirmed this
 * round, superseding TicketRevenueConsistencyPolicy.md's older blanket
 * "原則としてTicket Revenueは認識しない" no-show rule for this specific
 * hand-sold-ticket scenario): "NO_SHOWは...Sales recognition...には含めま
 * す". Delegates to `CheckInProcessor::processNoShow()` - the same
 * Revenue Recognition path `CheckInReservationUseCase` uses for a real
 * Check-in - so Accounting-enabled Productions still post the Journal
 * Entry, while NO_SHOW still never counts toward actual attendance
 * (no CheckIn Fact is created either way).
 */
final class MarkNoShowUseCase
{
    private ReservationRepositoryInterface $reservations;
    private PerformanceRepositoryInterface $performances;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private CheckInProcessor $processor;
    private TransactionManagerInterface $transactions;

    public function __construct(
        ReservationRepositoryInterface $reservations,
        PerformanceRepositoryInterface $performances,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        CheckInProcessor $processor,
        TransactionManagerInterface $transactions
    ) {
        $this->reservations = $reservations;
        $this->performances = $performances;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->processor = $processor;
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

        $productionId = $performance->productionId();

        $this->transactions->run(function () use ($reservation, $productionId, $requesterId): void {
            $this->processor->processNoShow($reservation, $productionId, $requesterId);
        });
    }
}
