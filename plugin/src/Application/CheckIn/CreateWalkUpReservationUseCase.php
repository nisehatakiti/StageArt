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
use StageArt\Core\Contract\MembershipContract;
use StageArt\Domain\CheckIn\CheckInRepositoryInterface;
use StageArt\Domain\IssuedTicket\IssuedTicket;
use StageArt\Domain\IssuedTicket\IssuedTicketRepositoryInterface;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationId;
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
 *
 * Phase 0-4統合監査 P1-3/P1-4 additions:
 * - Idempotency: `command->idempotencyKey` is checked BEFORE any
 *   validation/mutation, and recorded (via `WalkUpIdempotencyStoreInterface`)
 *   inside the same transaction that creates the Reservation. A retried
 *   confirmed action (same key) short-circuits to the original result
 *   without re-running Capacity/Ticket checks or creating a second
 *   Reservation/IssuedTicket/CheckIn/JournalEntry - see
 *   `buildIdempotentResult()`. A genuine concurrent race (two requests
 *   with the same key processed by two workers at once) is caught via
 *   the idempotency store's own UNIQUE-constraint-backed
 *   `WalkUpDuplicateRequestException`, not a time-window heuristic.
 * - Attribution validation: a non-null `attributedPersonId` must be an
 *   existing member of the target Production (Chapter 31 §3.1) - a data
 *   integrity check, not a change to who may SET the attribution
 *   (still gated by CheckInCapability::MANAGE, unchanged).
 */
final class CreateWalkUpReservationUseCase
{
    private PerformanceRepositoryInterface $performances;
    private TicketRepositoryInterface $tickets;
    private ReservationRepositoryInterface $reservations;
    private IssuedTicketRepositoryInterface $issuedTickets;
    private CheckInRepositoryInterface $checkIns;
    private IdentityContract $identity;
    private AuthorizationContract $authorization;
    private MembershipContract $membership;
    private CheckInProcessor $processor;
    private WalkUpIdempotencyStoreInterface $idempotencyStore;
    private TransactionManagerInterface $transactions;

    public function __construct(
        PerformanceRepositoryInterface $performances,
        TicketRepositoryInterface $tickets,
        ReservationRepositoryInterface $reservations,
        IssuedTicketRepositoryInterface $issuedTickets,
        CheckInRepositoryInterface $checkIns,
        IdentityContract $identity,
        AuthorizationContract $authorization,
        MembershipContract $membership,
        CheckInProcessor $processor,
        WalkUpIdempotencyStoreInterface $idempotencyStore,
        TransactionManagerInterface $transactions
    ) {
        $this->performances = $performances;
        $this->tickets = $tickets;
        $this->reservations = $reservations;
        $this->issuedTickets = $issuedTickets;
        $this->checkIns = $checkIns;
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->membership = $membership;
        $this->processor = $processor;
        $this->idempotencyStore = $idempotencyStore;
        $this->transactions = $transactions;
    }

    public function execute(CreateWalkUpReservationCommand $command): CheckInResult
    {
        $requesterId = $this->identity->resolveCurrentPersonId($command->requestedByWordPressUserId);

        if (! $requesterId) {
            throw new CheckInAccessDeniedException('No StageArt Person is linked to this WordPress user.');
        }

        $existingReservationId = $this->idempotencyStore->findReservationId($command->idempotencyKey);

        if ($existingReservationId !== null) {
            return $this->buildIdempotentResult($existingReservationId);
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

        if ($attributedPersonId !== null && ! $this->membership->isProductionMember($attributedPersonId, $performance->productionId())) {
            throw new AttributedPersonNotProductionMemberException();
        }

        try {
            $bundle = $this->transactions->run(function () use ($performanceId, $ticketId, $command, $ticket, $requesterId, $attributedPersonId, $performance) {
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

                $this->idempotencyStore->record($command->idempotencyKey, $reservation->id());

                return ['reservation' => $reservation, 'checkIn' => $checkIn];
            });
        } catch (WalkUpDuplicateRequestException $exception) {
            $existingReservationId = $this->idempotencyStore->findReservationId($command->idempotencyKey);

            if ($existingReservationId !== null) {
                return $this->buildIdempotentResult($existingReservationId);
            }

            throw $exception;
        }

        return CheckInResult::fromDomain($bundle['checkIn'], $bundle['reservation']);
    }

    private function buildIdempotentResult(ReservationId $reservationId): CheckInResult
    {
        $reservation = $this->reservations->findById($reservationId);
        $checkIn = $reservation !== null ? $this->checkIns->findLatestByReservationId($reservationId) : null;

        if ($reservation === null || $checkIn === null) {
            throw new InvalidArgumentException('Idempotency key resolved to a reservation_id with no matching Reservation/CheckIn record.');
        }

        return CheckInResult::fromDomain($checkIn, $reservation, true);
    }
}
