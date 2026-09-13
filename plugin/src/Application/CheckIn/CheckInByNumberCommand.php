<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

final class CheckInByNumberCommand
{
    public string $performanceId;
    public string $reservationNumber;
    public int $requestedByWordPressUserId;

    public function __construct(string $performanceId, string $reservationNumber, int $requestedByWordPressUserId)
    {
        $this->performanceId = $performanceId;
        $this->reservationNumber = $reservationNumber;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
