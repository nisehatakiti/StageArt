<?php

declare(strict_types=1);

namespace StageArt\Application\Ticket;

/**
 * StageArt Core/Module Architecture: Ticket Module's own Capability
 * vocabulary, requested from `AuthorizationContract::canForProduction()`.
 * Single umbrella capability (matching Rehearsal's `RehearsalCapability::
 * MANAGE` precedent and `docs/modules/Ticket.md`'s own suggested
 * `TicketCapability::MANAGE -> 'Ticket.Manage'`) gates every Ticket
 * Management mutation this Phase: Create/Update/Archive Ticket, and
 * updating Production's ticket sales/quota/ticket-back settings
 * (instruction §24 groups all of these under one TICKET_MANAGER
 * responsibility, not per-action Permissions).
 */
final class TicketCapability
{
    public const MANAGE = 'Ticket.Manage';

    private function __construct()
    {
    }
}
