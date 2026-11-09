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
 *
 * `Expense.Approve` (経費承認) is held by the PrimaryManager, a 会計管理者
 * (ACCOUNTING_MANAGER) and a 代理人 (PARTICIPANT_MANAGER +
 * REHEARSAL_MANAGER, see `ProductionAuthorizationService`) - StageArt 統合・
 * 会計仕様の確定事項 2026-10-28 §2, the same holders as
 * `ProductionCapability::COMPLETE` (決算確定).
 *
 * `Budget.Activate` (Budgetの確定) is the PrimaryManager's alone
 * (ProductionDelegatePolicy.md: "ProductionDelegateはBudget確定および決算完了の
 * 確定権限を持たない", Business Rules "Budget確定...はPrimaryManagerのみが
 * 行う"). No Role's Permission Set and no 代理人 rule grants it, so
 * `canForProduction()` succeeds only through its PrimaryManager rule -
 * unlike `Accounting.Update`, which a 会計管理者 holds for entering Budget
 * data (PR #7 review).
 */
final class AccountingCapability
{
    public const MANAGE = 'Accounting.Update';
    public const APPROVE_EXPENSE = 'Expense.Approve';
    public const ACTIVATE_BUDGET = 'Budget.Activate';

    private function __construct()
    {
    }
}
