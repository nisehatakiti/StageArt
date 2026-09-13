<?php

declare(strict_types=1);

namespace StageArt\Application\Reservation;

use RuntimeException;

/**
 * Covers both: (a) a self-service caller whose reservation number +
 * email pair does not match (§10/§32 - "一般予約者が他人のReservationを
 * 参照・変更できないようにする"), and (b) a WordPress-authenticated caller
 * who is not the PrimaryManager/RESERVATION_MANAGER for the admin-side
 * listing endpoint.
 */
final class ReservationAccessDeniedException extends RuntimeException
{
}
