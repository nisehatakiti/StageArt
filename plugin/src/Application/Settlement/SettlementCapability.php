<?php

declare(strict_types=1);

namespace StageArt\Application\Settlement;

/**
 * ProductionSettlementScreen.md (Chapter 29): settling a member's Ticket
 * Back pays out real money on the Production's behalf - the same
 * sensitivity `AccountingCapability::MANAGE` already carries. Granted to
 * RoleKey::ACCOUNTING_MANAGER (担当者権限をメンバー管理へ統合・整理 instruction
 * §会計担当仕様訂正: 会計担当 covers the Production's accounting処理全般,
 * Settlement included, not just Budget/Expense/JournalEntry) in addition
 * to PrimaryManager, via `RolePermissions::MAP`.
 */
final class SettlementCapability
{
    public const MANAGE = 'Settlement.Manage';

    private function __construct()
    {
    }
}
