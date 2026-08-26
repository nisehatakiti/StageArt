<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

/**
 * StageArt Core/Module Architecture
 * (docs/architecture/CoreModuleArchitecture.md): the Rehearsal Module's
 * own Capability vocabulary, requested from
 * `StageArt\Core\Contract\AuthorizationContract::canForProduction()`.
 * The string value is the pre-existing `Rehearsal.Update` Permission
 * (Authorization.md's REHEARSAL_MANAGER Role's Permission Set,
 * `RolePermissions::MAP`) - unchanged, so this refactor does not alter
 * who can do what, only which class is allowed to know the string.
 */
final class RehearsalCapability
{
    public const MANAGE = 'Rehearsal.Update';

    private function __construct()
    {
    }
}
