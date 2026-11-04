<?php

declare(strict_types=1);

namespace StageArt\Accounting;

use StageArt\Core\Contract\AuthorizationContract;
use StageArt\Core\Contract\IdentityContract;
use StageArt\Core\Contract\MembershipContract;
use StageArt\Core\Contract\NotificationContract;
use StageArt\Core\Contract\OrganizationContextContract;
use StageArt\Core\Contract\ProductionCapabilityDirectoryContract;
use StageArt\Core\Contract\ProductionContextContract;
use StageArt\Core\Module\ModuleDescriptor;

/**
 * StageArt Core/Module Architecture Phase 3 (continued): the Accounting
 * Module's own declared identity and Core-facing boundary, mirroring
 * `RehearsalModuleDescriptor` exactly. `NotificationContract` and
 * `ProductionCapabilityDirectoryContract` are required since 修正8: the
 * Expense approval workflow notifies the approvers of a submitted Expense
 * and the applicant of the outcome. Includes `OrganizationContextContract` - `CreateAccountUseCase`/
 * `ListAccountsUseCase` need `organizationExists()` (Phase 3's own
 * migration added this dependency to `AccountingModuleBootstrap`'s
 * constructor; this Descriptor had drifted out of sync with it until
 * Phase 4 §2's audit caught it - see `AccountingModuleBootstrap`'s own
 * constructor for the authoritative dependency list).
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
            OrganizationContextContract::class,
            IdentityContract::class,
            AuthorizationContract::class,
            MembershipContract::class,
            NotificationContract::class,
            ProductionCapabilityDirectoryContract::class,
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
            'stageart_expense_reviews',
            'stageart_legacy_expense_migration_audit',
        ];
    }
}
