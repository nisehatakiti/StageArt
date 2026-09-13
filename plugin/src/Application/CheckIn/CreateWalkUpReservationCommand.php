<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

final class CreateWalkUpReservationCommand
{
    public string $performanceId;
    public string $ticketId;
    public string $bookerName;
    public string $bookerEmail;
    public int $guestCount;
    public ?string $attributedPersonId;
    public int $requestedByWordPressUserId;

    public function __construct(
        string $performanceId,
        string $ticketId,
        string $bookerName,
        string $bookerEmail,
        int $guestCount,
        ?string $attributedPersonId,
        int $requestedByWordPressUserId
    ) {
        $this->performanceId = $performanceId;
        $this->ticketId = $ticketId;
        $this->bookerName = $bookerName;
        $this->bookerEmail = $bookerEmail;
        $this->guestCount = $guestCount;
        $this->attributedPersonId = $attributedPersonId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
