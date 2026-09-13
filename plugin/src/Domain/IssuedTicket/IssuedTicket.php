<?php

declare(strict_types=1);

namespace StageArt\Domain\IssuedTicket;

use DateTimeImmutable;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Domain\Ticket\TicketId;

/**
 * Phase 3 instruction §21/§22: "Reservation → Issued Ticket → QR Ticket
 * → Check In" - Issued Ticket is a distinct, independently-persisted
 * Entity ("Reservationとは別のEntity...Reservationに直接埋め込む簡略構造
 * にはしない"), not a boolean flag on Reservation. V1 generates exactly
 * one IssuedTicket per Reservation at creation time (single Ticket type,
 * single GuestCount - see Reservation::class's own docblock), but this
 * Entity's own shape (a plain record referencing a Reservation) does not
 * assume that 1:1 relationship structurally, so a later Phase adding
 * per-guest or multi-Ticket-type Reservations can extend it without
 * breaking this one.
 *
 * `performanceId`/`ticketId`/`guestCount` are denormalized copies of the
 * originating Reservation's own values at issuance time - convenient for
 * Check-in/attendance queries in a later Phase without a join back to
 * Reservation, and immutable here for the same Price-Snapshot-style
 * reason Reservation's own fields are frozen at creation.
 *
 * QR Ticket / Check-in themselves are out of Phase 3's scope (instruction
 * §48) - this Entity intentionally carries no QR code or Check-in Status
 * field yet, to avoid guessing at that later Phase's own design.
 */
final class IssuedTicket
{
    private IssuedTicketId $id;
    private ReservationId $reservationId;
    private PerformanceId $performanceId;
    private TicketId $ticketId;
    private int $guestCount;
    private DateTimeImmutable $issuedAt;

    private function __construct(
        IssuedTicketId $id,
        ReservationId $reservationId,
        PerformanceId $performanceId,
        TicketId $ticketId,
        int $guestCount,
        DateTimeImmutable $issuedAt
    ) {
        $this->id = $id;
        $this->reservationId = $reservationId;
        $this->performanceId = $performanceId;
        $this->ticketId = $ticketId;
        $this->guestCount = $guestCount;
        $this->issuedAt = $issuedAt;
    }

    public static function issueFor(ReservationId $reservationId, PerformanceId $performanceId, TicketId $ticketId, int $guestCount): self
    {
        return new self(
            IssuedTicketId::generate(),
            $reservationId,
            $performanceId,
            $ticketId,
            $guestCount,
            new DateTimeImmutable()
        );
    }

    public static function reconstitute(
        IssuedTicketId $id,
        ReservationId $reservationId,
        PerformanceId $performanceId,
        TicketId $ticketId,
        int $guestCount,
        DateTimeImmutable $issuedAt
    ): self {
        return new self($id, $reservationId, $performanceId, $ticketId, $guestCount, $issuedAt);
    }

    public function id(): IssuedTicketId
    {
        return $this->id;
    }

    public function reservationId(): ReservationId
    {
        return $this->reservationId;
    }

    public function performanceId(): PerformanceId
    {
        return $this->performanceId;
    }

    public function ticketId(): TicketId
    {
        return $this->ticketId;
    }

    public function guestCount(): int
    {
        return $this->guestCount;
    }

    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }
}
