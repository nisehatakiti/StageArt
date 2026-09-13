<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

use StageArt\Domain\CheckIn\CheckIn;
use StageArt\Domain\Reservation\Reservation;

final class CheckInResult
{
    public string $checkInId;
    public string $reservationId;
    public string $reservationNumber;
    public string $performanceId;
    public string $status;
    public string $reservationStatus;
    public string $checkedInBy;
    public string $checkedInAt;
    public bool $alreadyProcessed;

    private function __construct(
        string $checkInId,
        string $reservationId,
        string $reservationNumber,
        string $performanceId,
        string $status,
        string $reservationStatus,
        string $checkedInBy,
        string $checkedInAt,
        bool $alreadyProcessed
    ) {
        $this->checkInId = $checkInId;
        $this->reservationId = $reservationId;
        $this->reservationNumber = $reservationNumber;
        $this->performanceId = $performanceId;
        $this->status = $status;
        $this->reservationStatus = $reservationStatus;
        $this->checkedInBy = $checkedInBy;
        $this->checkedInAt = $checkedInAt;
        $this->alreadyProcessed = $alreadyProcessed;
    }

    public static function fromDomain(CheckIn $checkIn, Reservation $reservation, bool $alreadyProcessed = false): self
    {
        return new self(
            $checkIn->id()->toString(),
            $reservation->id()->toString(),
            $reservation->reservationNumber()->toString(),
            $checkIn->performanceId()->toString(),
            $checkIn->status()->toString(),
            $reservation->status()->toString(),
            $checkIn->checkedInBy()->toString(),
            $checkIn->checkedInAt()->format(DATE_ATOM),
            $alreadyProcessed
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'check_in_id' => $this->checkInId,
            'reservation_id' => $this->reservationId,
            'reservation_number' => $this->reservationNumber,
            'performance_id' => $this->performanceId,
            'status' => $this->status,
            'reservation_status' => $this->reservationStatus,
            'checked_in_by' => $this->checkedInBy,
            'checked_in_at' => $this->checkedInAt,
            'already_processed' => $this->alreadyProcessed,
        ];
    }
}
