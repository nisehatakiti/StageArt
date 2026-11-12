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
    /**
     * Phase 0-4統合監査 P1-3: a client-generated identifier, one per
     * confirmed Frontend action (the final "OK" in the two-step
     * confirmation flow) - required so retries/double-submits of the
     * exact same confirmed action reuse the same Reservation instead of
     * creating a duplicate.
     */
    public string $idempotencyKey;

    public function __construct(
        string $performanceId,
        string $ticketId,
        string $bookerName,
        string $bookerEmail,
        int $guestCount,
        ?string $attributedPersonId,
        int $requestedByWordPressUserId,
        string $idempotencyKey
    ) {
        $this->performanceId = $performanceId;
        $this->ticketId = $ticketId;
        $this->bookerName = $bookerName;
        $this->bookerEmail = $bookerEmail;
        $this->guestCount = $guestCount;
        $this->attributedPersonId = $attributedPersonId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->idempotencyKey = $idempotencyKey;
    }
}
