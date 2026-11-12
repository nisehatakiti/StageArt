<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

/**
 * StageArt Core/Module Architecture: gates only the WordPress-
 * authenticated ADMIN-side Reservation operations this Phase actually
 * has (listing a Performance's Reservations for management). Public
 * self-service Create/Get/Update/Cancel by reservation-number + email
 * (§10/§32) never checks this Capability at all - see
 * CreateReservationUseCase/UpdateReservationUseCase/
 * CancelReservationUseCase's own docblocks.
 */
final class ReservationCapability
{
    public const MANAGE = 'Reservation.Manage';

    private function __construct()
    {
    }
}
