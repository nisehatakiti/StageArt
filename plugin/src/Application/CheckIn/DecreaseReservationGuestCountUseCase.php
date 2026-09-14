<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Reservation\PerformanceAlreadyStartedException;
use StageArt\Application\Reservation\ReservationNotFoundException;
use StageArt\Application\Reservation\ReservationResult;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Domain\Reservation\ReservationModificationPolicy;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;

/**
 * Phase 5 (Production運営UI): reception's own day-of guest-count-decrease
 * action - e.g. a 4-guest Reservation where only 3 actually showed up,
 * fixed before Check-in per this Phase's confirmed scenario. This is
 * NOT a new capability: `Reservation::changeGuestCount()` and
 * `ReservationModificationPolicy::canDecreaseGuestCount()` already exist
 * (Phase 3) and already govern the public self-service equivalent
 * (`UpdateReservationUseCase`) - that UseCase is reception-unreachable
 * because it authenticates via reservation-number+email, not a StageArt
 * account. This UseCase is the same Domain operation through the
 * CheckInCapability::MANAGE-gated authenticated path instead, decrease-
 * only (an increase at the door has no walk-up-merge semantics this
 * Phase defines, so it is deliberately not offered here - a genuinely
 * new increase-ticket sale is a separate walk-up sale instead).
 */
final class DecreaseReservationGuestCountUseCase
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

    public function execute(DecreaseReservationGuestCountCommand $command): ReservationResult
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
                'Only the PrimaryManager or a ProductionDelegate with the CHECKIN_MANAGER Role can change a Reservation\'s guest count at reception.'
            );
        }

        if ($command->guestCount < 1) {
            throw new InvalidArgumentException('Guest count must be a positive integer.');
        }

        if ($command->guestCount >= $reservation->guestCount()) {
            throw new InvalidArgumentException('The new guest count must be less than the current guest count.');
        }

        if (! ReservationModificationPolicy::canDecreaseGuestCount(new DateTimeImmutable(), $performance->startDateTime())) {
            throw new PerformanceAlreadyStartedException('changed');
        }

        $this->transactions->run(function () use ($reservation, $command, $requesterId): void {
            $reservation->changeGuestCount($command->guestCount, $reservation->bookerName(), $reservation->bookerEmail(), $requesterId);
            $this->reservations->save($reservation);
        });

        return ReservationResult::fromDomain($reservation);
    }
}
