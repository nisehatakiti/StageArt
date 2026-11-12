<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

final class ChangeReservationAttributionCommand
{
    public string $reservationId;
    public ?string $attributedPersonId;
    public int $requestedByWordPressUserId;

    public function __construct(string $reservationId, ?string $attributedPersonId, int $requestedByWordPressUserId)
    {
        $this->reservationId = $reservationId;
        $this->attributedPersonId = $attributedPersonId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
