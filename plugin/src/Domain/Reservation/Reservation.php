<?php

declare(strict_types=1);

namespace StageArt\Domain\Reservation;

use DateTimeImmutable;
use InvalidArgumentException;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Ticket\TicketId;

/**
 * Reservation.md v6.0: AggregateRoot representing "誰が、どのPerformance
 * に、どのTicketで、何人分予約したか". Phase 3 instruction §11 confirms V1
 * is strictly 1 Reservation : 1 Performance : 1 Ticket : 1 GuestCount -
 * multiple Ticket types/quantities per Reservation, Companion, and seat
 * selection are all explicitly deferred (Reservation.md's own "# Future"
 * section already lists these, independent of this instruction).
 *
 * `bookerName`/`bookerEmail` are plain scalars, not a `PersonId`
 * reference: Reservation.md's "# General Audience" section states "一般
 * 観客にStageArtのInternal Portalへの参加を要求しない", and Phase 3
 * instruction §10 confirms self-service identity is
 * reservation-number + booking-email, not a StageArt account. This is
 * the same "plain nullable scalar for a Person without a formal account"
 * shape Participant::createNameOnly() already established in Phase 1
 * for an analogous need. `createdBy`/`updatedBy` stay nullable PersonId
 * (null = the general public booker acting via self-service; a real
 * PersonId = a WordPress-authenticated Production staff member acting on
 * someone's behalf), matching Reservation.md's "BookerとCreatedByは異なる
 * 場合がある" distinction.
 *
 * Phase 4 (Check-in/精算/会計連携): `attributedPersonId` is new -
 * ProductionSettlementScreen.md (Chapter 29) confirms Ticket Back
 * settlement is computed and settled per Production Member ("メンバー名
 * チケットバック未払い金"), so a genuinely separate "whose sales
 * performance does this Reservation count toward" fact is required -
 * this is NOT `createdBy` (the staff operator who happened to key in the
 * booking) and NOT the Booker (the paying customer, who is not a
 * StageArt Person at all in the general case). Deliberately nullable: a
 * self-service online booking or an unattributed walk-up/box-office sale
 * has no specific member to credit, and still counts toward Production-
 * wide Quota achievement without counting toward anyone's individual
 * Ticket Back. This field was not part of Phase 3's shipped Reservation
 * shape; its absence was confirmed a genuine gap during this Phase's
 * required pre-implementation research, not a silent behavior change.
 */
final class Reservation
{
    private ReservationId $id;
    private ReservationNumber $reservationNumber;
    private PerformanceId $performanceId;
    private TicketId $ticketId;
    private string $bookerName;
    private string $bookerEmail;
    private int $guestCount;
    private int $priceSnapshot;
    private ReservationStatus $status;
    private ?PersonId $attributedPersonId;
    private ?PersonId $createdBy;
    private DateTimeImmutable $createdAt;
    private ?PersonId $updatedBy;
    private DateTimeImmutable $updatedAt;

    private function __construct(
        ReservationId $id,
        ReservationNumber $reservationNumber,
        PerformanceId $performanceId,
        TicketId $ticketId,
        string $bookerName,
        string $bookerEmail,
        int $guestCount,
        int $priceSnapshot,
        ReservationStatus $status,
        ?PersonId $attributedPersonId,
        ?PersonId $createdBy,
        DateTimeImmutable $createdAt,
        ?PersonId $updatedBy,
        DateTimeImmutable $updatedAt
    ) {
        $this->id = $id;
        $this->reservationNumber = $reservationNumber;
        $this->performanceId = $performanceId;
        $this->ticketId = $ticketId;
        $this->bookerName = $bookerName;
        $this->bookerEmail = $bookerEmail;
        $this->guestCount = $guestCount;
        $this->priceSnapshot = $priceSnapshot;
        $this->status = $status;
        $this->attributedPersonId = $attributedPersonId;
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = $updatedAt;
    }

    public static function create(
        PerformanceId $performanceId,
        TicketId $ticketId,
        string $bookerName,
        string $bookerEmail,
        int $guestCount,
        int $priceSnapshot,
        ?PersonId $createdBy,
        ?PersonId $attributedPersonId = null
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            ReservationId::generate(),
            ReservationNumber::generate(),
            $performanceId,
            $ticketId,
            self::validateBookerName($bookerName),
            self::validateBookerEmail($bookerEmail),
            self::validateGuestCount($guestCount),
            self::validatePriceSnapshot($priceSnapshot),
            ReservationStatus::reserved(),
            $attributedPersonId,
            $createdBy,
            $now,
            $createdBy,
            $now
        );
    }

    public static function reconstitute(
        ReservationId $id,
        ReservationNumber $reservationNumber,
        PerformanceId $performanceId,
        TicketId $ticketId,
        string $bookerName,
        string $bookerEmail,
        int $guestCount,
        int $priceSnapshot,
        ReservationStatus $status,
        ?PersonId $createdBy,
        DateTimeImmutable $createdAt,
        ?PersonId $updatedBy,
        DateTimeImmutable $updatedAt,
        ?PersonId $attributedPersonId = null
    ): self {
        return new self(
            $id,
            $reservationNumber,
            $performanceId,
            $ticketId,
            $bookerName,
            $bookerEmail,
            $guestCount,
            $priceSnapshot,
            $status,
            $attributedPersonId,
            $createdBy,
            $createdAt,
            $updatedBy,
            $updatedAt
        );
    }

    /**
     * Corrects which Production Member (if any) this Reservation's sales
     * performance is attributed to. No status guard: this is
     * administrative bookkeeping metadata, not a booking-content change,
     * so a reception-desk correction must remain possible even after
     * Check-in (e.g. a walk-up sale initially logged with no attribution,
     * then assigned to the member who actually made the sale).
     */
    public function changeAttribution(?PersonId $attributedPersonId, ?PersonId $updatedBy): void
    {
        $this->attributedPersonId = $attributedPersonId;
        $this->touch($updatedBy);
    }

    /**
     * §12/§42: only GuestCount (and the Booker contact details) change
     * after creation - Performance, Ticket, and Price Snapshot are
     * immutable for the lifetime of a Reservation (Reservation.md's own
     * "Update Restriction" section explicitly forbids changing them).
     * Whether the specific new count is actually ALLOWED right now
     * (increase vs decrease, sales-end/performance-start cutoffs) is a
     * cross-Aggregate temporal policy decided by
     * `ReservationModificationPolicy` in the Application layer, not
     * here - this method only enforces the two invariants that belong to
     * Reservation itself: a positive GuestCount, and never touching a
     * CHECKED_IN or CANCELLED Reservation.
     */
    public function changeGuestCount(int $guestCount, string $bookerName, string $bookerEmail, ?PersonId $updatedBy): void
    {
        $this->guardModifiable();

        $this->guestCount = self::validateGuestCount($guestCount);
        $this->bookerName = self::validateBookerName($bookerName);
        $this->bookerEmail = self::validateBookerEmail($bookerEmail);
        $this->touch($updatedBy);
    }

    /**
     * §13: CANCELLED is terminal-but-idempotent at the Application layer
     * (see CancelReservationUseCase, which catches the double-cancel
     * exception this throws and returns success anyway) - the Domain
     * method itself still guards against re-cancelling, matching every
     * other cancel-not-delete Entity in this codebase (Performance,
     * Rehearsal).
     */
    public function cancel(?PersonId $updatedBy): void
    {
        if ($this->status->equals(ReservationStatus::fromString(ReservationStatus::CANCELLED))) {
            throw new InvalidArgumentException('Reservation is already CANCELLED.');
        }

        if ($this->status->equals(ReservationStatus::fromString(ReservationStatus::CHECKED_IN))) {
            throw new InvalidArgumentException('A CHECKED_IN Reservation cannot be cancelled.');
        }

        $this->status = ReservationStatus::fromString(ReservationStatus::CANCELLED);
        $this->touch($updatedBy);
    }

    /**
     * §13/§45: kept for a later Check-in Phase to call - not wired to
     * any UseCase or REST route this Phase.
     */
    public function checkIn(?PersonId $updatedBy): void
    {
        if (! $this->status->equals(ReservationStatus::fromString(ReservationStatus::RESERVED))) {
            throw new InvalidArgumentException('Only a RESERVED Reservation can be checked in.');
        }

        $this->status = ReservationStatus::fromString(ReservationStatus::CHECKED_IN);
        $this->touch($updatedBy);
    }

    /**
     * §13: kept for a later Phase - not wired to any UseCase or REST
     * route this Phase.
     */
    public function markNoShow(?PersonId $updatedBy): void
    {
        if (! $this->status->equals(ReservationStatus::fromString(ReservationStatus::RESERVED))) {
            throw new InvalidArgumentException('Only a RESERVED Reservation can be marked NO_SHOW.');
        }

        $this->status = ReservationStatus::fromString(ReservationStatus::NO_SHOW);
        $this->touch($updatedBy);
    }

    /**
     * Phase 4 (Check-in/精算/会計連携): the counterpart to checkIn() for
     * Check-in cancellation. CheckIn.md's "Check In Reversal" section
     * requires "Reservationの状態については、Reservation Domainのルールに
     * 従って更新する" without naming a specific target Status - reverting
     * to RESERVED (rather than leaving CHECKED_IN or inventing a new
     * Status) is the only choice consistent with Reservation's own
     * existing Status vocabulary and with a corrected Reservation being
     * eligible for a fresh, correct Check-in afterward.
     */
    public function reverseCheckIn(?PersonId $updatedBy): void
    {
        if (! $this->status->equals(ReservationStatus::fromString(ReservationStatus::CHECKED_IN))) {
            throw new InvalidArgumentException('Only a CHECKED_IN Reservation can have its Check-in reversed.');
        }

        $this->status = ReservationStatus::reserved();
        $this->touch($updatedBy);
    }

    /**
     * §14: whether this Reservation currently occupies a seat against
     * Performance.capacity - RESERVED/CHECKED_IN/NO_SHOW all still
     * consume capacity; only CANCELLED releases it.
     */
    public function occupiesCapacity(): bool
    {
        return ! $this->status->equals(ReservationStatus::fromString(ReservationStatus::CANCELLED));
    }

    private function guardModifiable(): void
    {
        if ($this->status->equals(ReservationStatus::fromString(ReservationStatus::CHECKED_IN))) {
            throw new InvalidArgumentException('A CHECKED_IN Reservation cannot be modified.');
        }

        if ($this->status->equals(ReservationStatus::fromString(ReservationStatus::CANCELLED))) {
            throw new InvalidArgumentException('A CANCELLED Reservation cannot be modified.');
        }
    }

    private static function validateBookerName(string $bookerName): string
    {
        $trimmed = trim($bookerName);

        if ($trimmed === '') {
            throw new InvalidArgumentException('Booker name must not be empty.');
        }

        return $trimmed;
    }

    private static function validateBookerEmail(string $bookerEmail): string
    {
        $trimmed = trim($bookerEmail);

        if ($trimmed === '' || ! filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid booker email: {$bookerEmail}");
        }

        return $trimmed;
    }

    private static function validateGuestCount(int $guestCount): int
    {
        if ($guestCount < 1) {
            throw new InvalidArgumentException('Guest count must be a positive integer.');
        }

        return $guestCount;
    }

    private static function validatePriceSnapshot(int $priceSnapshot): int
    {
        if ($priceSnapshot <= 0) {
            throw new InvalidArgumentException('Price snapshot must be a positive integer.');
        }

        return $priceSnapshot;
    }

    private function touch(?PersonId $updatedBy): void
    {
        $this->updatedBy = $updatedBy;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): ReservationId
    {
        return $this->id;
    }

    public function reservationNumber(): ReservationNumber
    {
        return $this->reservationNumber;
    }

    public function performanceId(): PerformanceId
    {
        return $this->performanceId;
    }

    public function ticketId(): TicketId
    {
        return $this->ticketId;
    }

    public function bookerName(): string
    {
        return $this->bookerName;
    }

    public function bookerEmail(): string
    {
        return $this->bookerEmail;
    }

    public function guestCount(): int
    {
        return $this->guestCount;
    }

    public function priceSnapshot(): int
    {
        return $this->priceSnapshot;
    }

    public function status(): ReservationStatus
    {
        return $this->status;
    }

    public function attributedPersonId(): ?PersonId
    {
        return $this->attributedPersonId;
    }

    public function createdBy(): ?PersonId
    {
        return $this->createdBy;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedBy(): ?PersonId
    {
        return $this->updatedBy;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
