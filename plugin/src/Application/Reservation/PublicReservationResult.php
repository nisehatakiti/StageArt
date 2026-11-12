<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use StageArt\Domain\Reservation\Reservation;

/**
 * Phase 0-4統合監査 P1-2: the general-audience-facing counterpart to
 * `ReservationResult`, used ONLY by the public self-service Reservation
 * endpoints (create/lookup/update/cancel in ReservationRestController -
 * all `permission_callback: '__return_true'`). `ReservationResult`
 * itself stays unchanged and keeps being used by every authenticated
 * management/reception endpoint (admin listing, Check-in search,
 * attribution change) that genuinely needs `attributed_person_id`/
 * `created_by`/`updated_by`.
 *
 * Deliberately excludes exactly the three fields the audit identified as
 * leaking internal information to an unauthenticated general-audience
 * booker: `attributed_person_id` ("誰扱い" - which Production Member a
 * sale is credited to, irrelevant and none of a booker's business),
 * `created_by`/`updated_by` (internal StageArt Person UUIDs of whichever
 * staff member last touched the booking). Every other field
 * (`bookerName`/`bookerEmail`/etc.) is data the caller already supplied
 * or is entitled to see about their own booking.
 */
final class PublicReservationResult
{
    public string $id;
    public string $reservationNumber;
    public string $performanceId;
    public string $ticketId;
    public string $bookerName;
    public string $bookerEmail;
    public int $guestCount;
    public int $priceSnapshot;
    public string $status;
    public string $createdAt;
    public string $updatedAt;

    private function __construct(
        string $id,
        string $reservationNumber,
        string $performanceId,
        string $ticketId,
        string $bookerName,
        string $bookerEmail,
        int $guestCount,
        int $priceSnapshot,
        string $status,
        string $createdAt,
        string $updatedAt
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
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public static function fromDomain(Reservation $reservation): self
    {
        return new self(
            $reservation->id()->toString(),
            $reservation->reservationNumber()->toString(),
            $reservation->performanceId()->toString(),
            $reservation->ticketId()->toString(),
            $reservation->bookerName(),
            $reservation->bookerEmail(),
            $reservation->guestCount(),
            $reservation->priceSnapshot(),
            $reservation->status()->toString(),
            $reservation->createdAt()->format(DATE_ATOM),
            $reservation->updatedAt()->format(DATE_ATOM)
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reservation_number' => $this->reservationNumber,
            'performance_id' => $this->performanceId,
            'ticket_id' => $this->ticketId,
            'booker_name' => $this->bookerName,
            'booker_email' => $this->bookerEmail,
            'guest_count' => $this->guestCount,
            'price_snapshot' => $this->priceSnapshot,
            'status' => $this->status,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
