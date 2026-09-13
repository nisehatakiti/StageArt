<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use StageArt\Domain\Reservation\Reservation;

final class ReservationResult
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
    public ?string $createdBy;
    public string $createdAt;
    public ?string $updatedBy;
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
        ?string $createdBy,
        string $createdAt,
        ?string $updatedBy,
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
        $this->createdBy = $createdBy;
        $this->createdAt = $createdAt;
        $this->updatedBy = $updatedBy;
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
            $reservation->createdBy()?->toString(),
            $reservation->createdAt()->format(DATE_ATOM),
            $reservation->updatedBy()?->toString(),
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
            'created_by' => $this->createdBy,
            'created_at' => $this->createdAt,
            'updated_by' => $this->updatedBy,
            'updated_at' => $this->updatedAt,
        ];
    }
}
