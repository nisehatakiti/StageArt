<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use StageArt\Domain\Reservation\Reservation;

/**
 * Phase 3 instruction §10/§32: self-service identity is reservation
 * number + booking email, not a StageArt account. Shared by every
 * public Get/Update/Cancel UseCase so the comparison (case-insensitive,
 * trimmed) is applied identically everywhere rather than re-implemented
 * per call site.
 */
final class SelfServiceAuthenticator
{
    private function __construct()
    {
    }

    public static function verify(Reservation $reservation, string $email): void
    {
        if (strtolower(trim($email)) !== strtolower($reservation->bookerEmail())) {
            throw new ReservationAccessDeniedException('Reservation number and email do not match.');
        }
    }
}
