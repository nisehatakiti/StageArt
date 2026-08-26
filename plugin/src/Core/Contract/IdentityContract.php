<?php

declare(strict_types=1);

namespace StageArt\Core\Contract;

use StageArt\Domain\Person\PersonId;

/**
 * StageArt Core/Module Architecture: resolves the platform's own
 * WordPress-based Identity into a StageArt PersonId - kept as its own
 * Contract (distinct from AuthorizationContract, which also exposes
 * `resolveCurrentPersonId()` for convenience on the same call path)
 * since a future WordPress Adapter swap
 * (`03-ModularArchitecture.md` §7/§12) only needs to replace *this*
 * piece; the Capability-evaluation logic in AuthorizationContract does
 * not depend on WordPress at all.
 */
interface IdentityContract
{
    public function resolveCurrentPersonId(int $wordPressUserId): ?PersonId;
}
