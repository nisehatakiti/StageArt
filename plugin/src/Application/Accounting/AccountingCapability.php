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
 * PR #8 統合 (2026-11-24): every Capability here - `Accounting.Update`
 * (予算策定・編集, 経費入力・編集 including others' Expenses, 実績・仕訳),
 * `Expense.Approve` (経費承認, 予算実績の確定) and `Budget.Activate` (予算の
 * 確定) - is held by the same three: the PrimaryManager
 * (`canForProduction()`'s own PrimaryManager-always-succeeds rule), a
 * 会計担当 (RoleKey::ACCOUNTING_MANAGER via `RolePermissions::MAP`) and a
 * 公演代理人 (PARTICIPANT_MANAGER + REHEARSAL_MANAGER, through
 * `ProductionAuthorizationService::PROXY_MANAGER_CAPABILITIES`).
 * `SettlementCapability::MANAGE` (決算・精算の最終確定) has the same holders.
 *
 * History: `Budget.Activate` was the PrimaryManager's alone until this
 * change (ProductionDelegatePolicy.md's earlier "ProductionDelegateはBudget
 * 確定および決算完了の確定権限を持たない"), and a 公演代理人 held only
 * `Expense.Approve` (統合・会計仕様の確定事項 2026-10-28 §2). 公演の完了
 * (`ProductionAuthorizationService::canCompleteProduction()`) is not an
 * accounting Capability and stays PrimaryManager-only.
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
