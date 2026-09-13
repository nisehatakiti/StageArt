<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

final class CreateReservationCommand
{
    public string $performanceId;
    public string $ticketId;
    public string $bookerName;
    public string $bookerEmail;
    public int $guestCount;
    /** null for a general-audience self-service booking (§10); a real
     * WordPress user id when Production staff creates it on someone's
     * behalf. */
    public ?int $createdByWordPressUserId;

    public function __construct(
        string $performanceId,
        string $ticketId,
        string $bookerName,
        string $bookerEmail,
        int $guestCount,
        ?int $createdByWordPressUserId = null
    ) {
        $this->performanceId = $performanceId;
        $this->ticketId = $ticketId;
        $this->bookerName = $bookerName;
        $this->bookerEmail = $bookerEmail;
        $this->guestCount = $guestCount;
        $this->createdByWordPressUserId = $createdByWordPressUserId;
    }
}
