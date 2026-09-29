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
 * `Accounting.Update` is granted to RoleKey::ACCOUNTING_MANAGER via
 * `RolePermissions::MAP` (担当者権限をメンバー管理へ統合 instruction §会計担当),
 * in addition to PrimaryManager (`canForProduction()`'s own
 * PrimaryManager-always-succeeds rule).
 */
final class AccountingCapability
{
    public const MANAGE = 'Accounting.Update';

    private function __construct()
    {
    }
}
