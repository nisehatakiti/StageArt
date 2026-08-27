<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * StageArt Core/Module Architecture (docs/architecture/CoreModuleArchitecture.md):
 * the single generic Authorization boundary a Domain Module depends on.
 * Deliberately has no method named after any Module ("canManageRehearsals",
 * "canManageTicket", "canManageAccounting" etc.) - a Module requests
 * whatever Capability string it owns the meaning of
 * (`RehearsalCapability::MANAGE`, a future `TicketCapability::MANAGE`,
 * `AccountingCapability::MANAGE`), and Core evaluates it generically
 * against the Production-Scope Role/Permission structure it already
 * owns (see CoreAuthorizationAdapter) - Core never hardcodes which
 * Capability strings exist or what they mean.
 */
interface AuthorizationContract
{
    /**
     * Resolves the current request's Identity - the one WordPress-aware
     * entry point every Module needs, since a Module's own UseCases are
     * invoked with only a WordPress user id (from the REST layer), never
     * a StageArt PersonId directly.
     */
    public function resolveCurrentPersonId(int $wordPressUserId): ?PersonId;

    /**
     * Generic Capability check within a single Production's Context.
     * The Production's own PrimaryManager always succeeds (a Core-level
     * rule, not a Module concern - see CoreAuthorizationAdapter); beyond
     * that, an ACTIVE ProductionDelegate's Role must include the given
     * Capability string in its Permission Set. An unrecognized
     * Capability string simply evaluates to false for any non-
     * PrimaryManager caller - Core does not need to know it exists
     * ahead of time.
     */
    public function canForProduction(PersonId $personId, ProductionId $productionId, string $capability): bool;

    /**
     * Phase 3: the Organization-Scope counterpart to `canForProduction()`
     * - for the rarer case where a Module needs an Organization-level
     * Capability check not tied to any one Production (e.g.
     * `OrganizationCapability::OWNER`, requested by Accounting's
     * `PostJournalEntryUseCase` for an Organization-level JournalEntry).
     * Same "Core does not need to know the Capability exists ahead of
     * time" contract as `canForProduction()` - an unrecognized string
     * simply evaluates to false.
     */
    public function canForOrganization(PersonId $personId, OrganizationId $organizationId, string $capability): bool;
}
