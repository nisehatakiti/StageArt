<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use InvalidArgumentException;
use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Reservation\CapacityExceededException;
use StageArt\Application\Reservation\PerformanceAlreadyStartedException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Application\Ticket\TicketNotFoundException;
use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Domain\IssuedTicket\IssuedTicket;
use StageArt\Domain\IssuedTicket\IssuedTicketRepositoryInterface;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Ticket\TicketId;
use StageArt\Domain\Ticket\TicketRepositoryInterface;
use DateTimeImmutable;

/**
 * CheckIn.md's own framing of 当日券 ("walk-up ticket"): "特別なModelでは
 * ない...予約を作成し、即座にCheck Inする、通常のTicket/Reservation Entityを
 * 利用する構成". This deliberately does NOT reuse CreateReservationUseCase:
 * that UseCase enforces the *public online sales window*
 * (`SalesWindowResolver` publication/start/end rules), which has no
 * bearing on a reception-desk sale happening in person at the venue -
 * only Capacity and "the Performance has not already started" remain
 * relevant here. The created Reservation and its immediate Check-in
 * (via the same `CheckInProcessor` every other Check-in path uses) are
 * one atomic transaction, so a walk-up sale can never end up "sold but
 * not checked in" or vice versa.
 */
final class CreateWalkUpReservationUseCase
{
    private PerformanceRepositoryInterface $performances;
    private TicketRepositoryInterface $tickets;
    private ReservationRepositoryInterface $reservations;
    private IssuedTicketRepositoryInterface $issuedTickets;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private CheckInProcessor $processor;
    private TransactionManagerInterface $transactions;

    public function __construct(
        PerformanceRepositoryInterface $performances,
        TicketRepositoryInterface $tickets,
        ReservationRepositoryInterface $reservations,
        IssuedTicketRepositoryInterface $issuedTickets,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        CheckInProcessor $processor,
        TransactionManagerInterface $transactions
    ) {
        $this->performances = $performances;
        $this->tickets = $tickets;
        $this->reservations = $reservations;
        $this->issuedTickets = $issuedTickets;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->processor = $processor;
        $this->transactions = $transactions;
    }

    public function execute(CreateWalkUpReservationCommand $command): CheckInResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new CheckInAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $performanceId = PerformanceId::fromString($command->performanceId);
        $performance = $this->performances->findById($performanceId);

        if (! $performance) {
            throw new PerformanceNotFoundException($command->performanceId);
        }

        if (! $this->authorization->canForProduction($requesterId, $performance->productionId(), CheckInCapability::MANAGE)) {
            throw new CheckInAccessDeniedException(
                'Only the PrimaryManager or a ProductionDelegate with the CHECKIN_MANAGER Role can sell a walk-up ticket.'
            );
        }

        $ticketId = TicketId::fromString($command->ticketId);
        $ticket = $this->tickets->findById($ticketId);

        if (! $ticket || ! $ticket->productionId()->equals($performance->productionId()) || ! $ticket->isActive()) {
            throw new TicketNotFoundException($command->ticketId);
        }

        if ($command->guestCount < 1) {
            throw new InvalidArgumentException('Guest count must be a positive integer.');
        }

        if (new DateTimeImmutable() >= $performance->startDateTime()) {
            throw new PerformanceAlreadyStartedException('sold as a walk-up ticket');
        }

        $existingReservations = $this->reservations->findByPerformanceId($performanceId);
        $occupied = array_sum(array_map(
            static fn (Reservation $r): int => $r->occupiesCapacity() ? $r->guestCount() : 0,
            $existingReservations
        ));

        if ($occupied + $command->guestCount > $performance->capacity()) {
            throw new CapacityExceededException();
        }

        $attributedPersonId = $command->attributedPersonId !== null
            ? PersonId::fromString($command->attributedPersonId)
            : null;

        $checkIn = $this->transactions->run(function () use ($performanceId, $ticketId, $command, $ticket, $requesterId, $attributedPersonId, $performance) {
            $reservation = Reservation::create(
                $performanceId,
                $ticketId,
                $command->bookerName,
                $command->bookerEmail,
                $command->guestCount,
                $ticket->price(),
                $requesterId,
                $attributedPersonId
            );

            $this->reservations->save($reservation);

            $issuedTicket = IssuedTicket::issueFor($reservation->id(), $performanceId, $ticketId, $reservation->guestCount());
            $this->issuedTickets->save($issuedTicket);

            $checkIn = $this->processor->process($reservation, $performance->productionId(), $requesterId);

            return ['reservation' => $reservation, 'checkIn' => $checkIn];
        });

        return CheckInResult::fromDomain($checkIn['checkIn'], $checkIn['reservation']);
    }
}
