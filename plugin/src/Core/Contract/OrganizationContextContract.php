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
}
