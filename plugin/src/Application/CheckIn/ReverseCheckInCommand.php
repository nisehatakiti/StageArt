<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

final class ReverseCheckInCommand
{
    public string $performanceId;
    public string $reservationId;
    public int $requestedByWordPressUserId;

    public function __construct(string $performanceId, string $reservationId, int $requestedByWordPressUserId)
    {
        $this->performanceId = $performanceId;
        $this->reservationId = $reservationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
