<?php

declare(strict_types=1);

namespace StageArt\Application\CheckIn;

/**
 * StageArt Core/Module Architecture: the Check-in Module's own Capability
 * string, requested from `AuthorizationContract::canForProduction()` -
 * same "Core never hardcodes Capability strings" contract as
 * `TicketCapability`/`ReservationCapability`/`PerformanceCapability`.
 * Deliberately narrow: reception-desk actions only (Check-in/Reservation
 * search/day-of guest-count change/walk-up ticket/Check-in cancel).
 * Settlement and Accounting-close are explicitly NOT covered by this
 * Capability - see `Application\Settlement\SettlementCapability`.
 */
final class CheckInCapability
{
    public const MANAGE = 'CheckIn.Manage';

    private function __construct()
    {
    }
}
