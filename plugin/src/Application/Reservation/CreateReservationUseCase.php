<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Application\Performance\PerformanceNotFoundException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Application\Shared\TransactionManagerInterface;
use StageArt\Application\Ticket\TicketNotFoundException;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Domain\IssuedTicket\IssuedTicket;
use StageArt\Domain\IssuedTicket\IssuedTicketRepositoryInterface;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Performance\PerformanceRepositoryInterface;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Reservation\ReservationRepositoryInterface;
use StageArt\Domain\Ticket\TicketId;
use StageArt\Domain\Ticket\TicketRepositoryInterface;

/**
 * Phase 3 instruction §40 - the public-facing Reservation creation flow.
 * Deliberately NOT gated by `AuthorizationContract`/`MembershipContract`:
 * a Reservation is made by a general audience member who has no
 * StageArt Person/WordPress account at all (Reservation.md's "一般観客に
 * StageArtのInternal Portalへの参加を要求しない" - see
 * `Domain\Reservation\Reservation`'s own docblock). `createdByWordPressUserId`
 * is only resolved to a PersonId when a Production staff member is
 * making the booking on someone's behalf; a null/unresolvable value is
 * not an error here, it is the normal self-service case.
 *
 * Depends directly on `Domain\Performance\PerformanceRepositoryInterface`
 * and `Domain\Ticket\TicketRepositoryInterface` (Domain-layer
 * interfaces, matching this Phase's established direct-Domain-dependency
 * precedent - see UpdateProductionUseCase/UpdateTicketSalesSettingsUseCase),
 * not either Module's own Application internals.
 */
final class CreateReservationUseCase
{
    private PerformanceRepositoryInterface $performances;
    private TicketRepositoryInterface $tickets;
    private ReservationRepositoryInterface $reservations;
    private IssuedTicketRepositoryInterface $issuedTickets;
    private ProductionContextContract $productionContext;
    private IdentityContract $identity;
    private TransactionManagerInterface $transactions;

    public function __construct(
        PerformanceRepositoryInterface $performances,
        TicketRepositoryInterface $tickets,
        ReservationRepositoryInterface $reservations,
        IssuedTicketRepositoryInterface $issuedTickets,
        ProductionContextContract $productionContext,
        IdentityContract $identity,
        TransactionManagerInterface $transactions
    ) {
        $this->performances = $performances;
        $this->tickets = $tickets;
        $this->reservations = $reservations;
        $this->issuedTickets = $issuedTickets;
        $this->productionContext = $productionContext;
        $this->identity = $identity;
        $this->transactions = $transactions;
    }

    public function execute(CreateReservationCommand $command): PublicReservationResult
    {
        // 1. Performance存在確認
        $performanceId = PerformanceId::fromString($command->performanceId);
        $performance = $this->performances->findById($performanceId);

        if (! $performance) {
            throw new PerformanceNotFoundException($command->performanceId);
        }

        // 2. Ticket存在確認
        $ticketId = TicketId::fromString($command->ticketId);
        $ticket = $this->tickets->findById($ticketId);

        if (! $ticket) {
            throw new TicketNotFoundException($command->ticketId);
        }

        // 3. Ticketが対象Productionに属していることを確認
        if (! $ticket->productionId()->equals($performance->productionId())) {
            throw new InvalidArgumentException('This Ticket does not belong to the Performance\'s Production.');
        }

        if (! $ticket->isActive()) {
            throw new TicketNotFoundException($command->ticketId);
        }

        $settings = $this->productionContext->getProductionTicketSettings($performance->productionId());

        if ($settings === null) {
            throw new ProductionNotFoundException($performance->productionId()->toString());
        }

        // 4. Ticket公開状態確認
        if (! SalesWindowResolver::isTicketPublished($settings)) {
            throw new TicketNotPublicException();
        }

        $now = new DateTimeImmutable();
        $performanceStart = $performance->startDateTime();

        // 5. 販売開始日時確認
        $salesStartAt = SalesWindowResolver::salesStartAt($settings);

        if ($salesStartAt === null || $now < $salesStartAt) {
            throw new SalesNotStartedException();
        }

        // 6. 販売終了日時確認
        $salesEndAt = SalesWindowResolver::salesEndAt($settings, $performanceStart);

        if ($salesEndAt !== null && $now >= $salesEndAt) {
            throw new SalesEndedException();
        }

        if ($now >= $performanceStart) {
            throw new PerformanceAlreadyStartedException('created');
        }

        // 7. GuestCountバリデーション
        if ($command->guestCount < 1) {
            throw new InvalidArgumentException('Guest count must be a positive integer.');
        }

        // 8. Capacity確認
        $existingReservations = $this->reservations->findByPerformanceId($performanceId);
        $occupied = array_sum(array_map(
            static fn (Reservation $r): int => $r->occupiesCapacity() ? $r->guestCount() : 0,
            $existingReservations
        ));

        if ($occupied + $command->guestCount > $performance->capacity()) {
            throw new CapacityExceededException();
        }

        // 9-10. Ticket価格取得 / Price Snapshot生成 は Reservation::create() 内
        $createdBy = $command->createdByWordPressUserId !== null
            ? $this->identity->resolveCurrentPersonId($command->createdByWordPressUserId)
            : null;

        // 11-13. Reservation生成 / Issued Ticket生成 / Transaction内で保存
        $reservation = $this->transactions->run(function () use ($performanceId, $ticketId, $command, $ticket, $createdBy): Reservation {
            $reservation = Reservation::create(
                $performanceId,
                $ticketId,
                $command->bookerName,
                $command->bookerEmail,
                $command->guestCount,
                $ticket->price(),
                $createdBy
            );

            $this->reservations->save($reservation);

            $issuedTicket = IssuedTicket::issueFor($reservation->id(), $performanceId, $ticketId, $reservation->guestCount());
            $this->issuedTickets->save($issuedTicket);

            return $reservation;
        });

        return PublicReservationResult::fromDomain($reservation);
    }
}
