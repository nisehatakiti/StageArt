<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Organization\OrganizationId;

/**
 * StageArt Core/Module Architecture: read-only Organization existence/
 * identity access for Domain Modules whose Context is Organization-
 * scoped rather than Production-scoped (a future Ticket or Accounting
 * concern that spans an Organization directly, not just one
 * Production). No current Module (Rehearsal) needs this yet - defined
 * now so the Contract surface is complete, per this phase's explicit
 * "Core Contractを作成してください" requirement, not because Rehearsal
 * depends on it.
 */
interface OrganizationContextContract
{
    public function organizationExists(OrganizationId $organizationId): bool;

    /**
     * Phase 4 Check-in/精算/会計連携: whether ticket Check-in Revenue
     * Recognition should generate a Journal Entry for this Organization.
     * "Accounting OFF" is not an error condition - Reservation/Check-in/
     * sales/Ticket-Back/Quota/Settlement data is still tracked internally
     * either way (see this Phase's report); only the Journal Entry side
     * effect is conditional on this flag.
     */
    public function isAccountingEnabled(OrganizationId $organizationId): bool;
}
