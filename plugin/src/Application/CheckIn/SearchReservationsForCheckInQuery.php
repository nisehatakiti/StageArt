<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

final class SearchReservationsForCheckInQuery
{
    public string $performanceId;
    public ?string $keyword;
    public int $requestedByWordPressUserId;

    public function __construct(string $performanceId, ?string $keyword, int $requestedByWordPressUserId)
    {
        $this->performanceId = $performanceId;
        $this->keyword = $keyword;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
