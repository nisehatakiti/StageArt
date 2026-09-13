<?php

declare(strict_types=1);

namespace StageArt\Application\Settlement;

/**
 * ProductionSettlementScreen.md (Chapter 29): settling a member's Ticket
 * Back pays out real money on the Production's behalf - the same
 * sensitivity `AccountingCapability::MANAGE` already carries. No RoleKey
 * currently grants this in `RolePermissions::MAP`, so
 * `AuthorizationContract::canForProduction()` evaluates it to
 * PrimaryManager-only today, mirroring AccountingCapability's own
 * documented precedent exactly (see that class's docblock).
 */
final class SettlementCapability
{
    public const MANAGE = 'Settlement.Manage';

    private function __construct()
    {
    }
}
