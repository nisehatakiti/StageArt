<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

final class ListReservationsForPerformanceQuery
{
    public string $performanceId;
    public int $requestedByWordPressUserId;

    public function __construct(string $performanceId, int $requestedByWordPressUserId)
    {
        $this->performanceId = $performanceId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
