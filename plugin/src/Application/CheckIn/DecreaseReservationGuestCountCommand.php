<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

final class DecreaseReservationGuestCountCommand
{
    public string $performanceId;
    public string $reservationId;
    public int $guestCount;
    public int $requestedByWordPressUserId;

    public function __construct(string $performanceId, string $reservationId, int $guestCount, int $requestedByWordPressUserId)
    {
        $this->performanceId = $performanceId;
        $this->reservationId = $reservationId;
        $this->guestCount = $guestCount;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
