<?php

declare(strict_types=1);

namespace StageArt\Application\Accounting;

/**
 * StageArt Core/Module Architecture
 * (docs/architecture/CoreModuleArchitecture.md): the Accounting
 * Module's own Capability vocabulary (spanning
 * `Application\Budget`/`Expense`/`JournalEntry`/`Account`/
 * `ProductionAccounting` - there is no single Accounting Application
 * namespace yet, this class is the first shared piece of one),
 * requested from `StageArt\Core\Contract\AuthorizationContract::
 * canForProduction()`.
 *
 * `Accounting.Update` is a new Permission string - no Role's Permission
 * Set currently grants it (`RolePermissions::MAP` has no
 * ACCOUNTING_MANAGER entry, same as before this refactor - see the
 * removed `ProductionAuthorizationService::canManageAccounting()`'s own
 * prior docblock), so `canForProduction()` evaluates this capability to
 * PrimaryManager-only today, identical to the pre-refactor behavior.
 * Extending it to a real ACCOUNTING_MANAGER Role/Permission Set is real
 * Accounting Domain design work, explicitly out of this phase's scope.
 */
final class AccountingCapability
{
    public const MANAGE = 'Accounting.Update';

    private function __construct()
    {
    }
}
