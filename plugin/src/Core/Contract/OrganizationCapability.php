<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

/**
 * StageArt Core/Module Architecture Phase 3: Organization-Scope
 * Capability strings, the counterpart to each Module's own
 * `RehearsalCapability`/`AccountingCapability` (Production-Scope) - but
 * owned by Core itself, not by any one Module, since "is this Person
 * the Organization Owner" is a generic Core concept any Module may need
 * (today: Accounting's `PostJournalEntryUseCase`, for a JournalEntry
 * with no Production - see that class's own docblock), not something
 * specific to Accounting's own domain. Requested from
 * `AuthorizationContract::canForOrganization()`.
 */
final class OrganizationCapability
{
    public const OWNER = 'Organization.Owner';

    /**
     * Any ACTIVE Organization Membership (OWNER or MEMBER) - the
     * broader "is this Person part of this Organization at all" check,
     * distinct from `OWNER`'s narrower "is this Person specifically the
     * Owner". `CreateAccountUseCase`/`ListAccountsUseCase` need both:
     * Create is OWNER-only, List is any Member.
     */
    public const MEMBER = 'Organization.Member';

    private function __construct()
    {
    }
}
