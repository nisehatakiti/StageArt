<?php

declare(strict_types=1);

namespace StageArt\Accounting;

use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Core\Module\ModuleDescriptor;

/**
 * StageArt Core/Module Architecture Phase 3 (continued): the Accounting
 * Module's own declared identity and Core-facing boundary, mirroring
 * `RehearsalModuleDescriptor` exactly. `requiredContracts()` omits
 * `NotificationContract` - unlike Rehearsal, no Accounting UseCase
 * currently calls it (disclosed, not a gap this class needs to paper
 * over).
 */
final class AccountingModuleDescriptor implements ModuleDescriptor
{
    public function moduleId(): string
    {
        return 'accounting';
    }

    public function version(): string
    {
        return '3.0.0';
    }

    public function requiredCoreVersion(): string
    {
        return '0.1.0';
    }

    public function requiredContracts(): array
    {
        return [
            ProductionContextContract::class,
            IdentityContract::class,
            AuthorizationContract::class,
            MembershipContract::class,
        ];
    }

    public function ownedTables(): array
    {
        return [
            'stageart_accounts',
            'stageart_budgets',
            'stageart_budget_lines',
            'stageart_journal_entries',
            'stageart_journal_entry_lines',
            'stageart_expenses',
            'stageart_expense_lines',
        ];
    }
}
