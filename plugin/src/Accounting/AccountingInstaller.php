<?php

declare(strict_types=1);

namespace StageArt\Accounting;

/**
 * StageArt Core/Module Architecture Phase 3 (docs/architecture/
 * WordPressPluginModuleBoundary.md §10): the Accounting Module's own 7
 * tables' CREATE statements, moved here verbatim (byte-identical SQL)
 * from the previously-monolithic
 * `Infrastructure\WordPress\Schema\Installer::install()`, mirroring
 * `RehearsalInstaller`'s own shape exactly. Core's own installer now
 * delegates to this class rather than creating these tables itself.
 *
 * No SQL text differs from the pre-extraction monolithic Installer -
 * this is a pure extraction, not a schema change. dbDelta remains
 * idempotent against an already-current schema, so re-running this
 * against an existing StageArt install is exactly as safe as before.
 */
final class AccountingInstaller
{
    /**
     * @param \wpdb $wpdb
     */
    public static function install($wpdb, string $charsetCollate): void
    {
        $accounts = $wpdb->prefix . 'stageart_accounts';
        $budgets = $wpdb->prefix . 'stageart_budgets';
        $budgetLines = $wpdb->prefix . 'stageart_budget_lines';
        $journalEntries = $wpdb->prefix . 'stageart_journal_entries';
        $journalEntryLines = $wpdb->prefix . 'stageart_journal_entry_lines';
        $expenses = $wpdb->prefix . 'stageart_expenses';
        $expenseLines = $wpdb->prefix . 'stageart_expense_lines';

        /*
         * Phase 6.0 Accounting Foundation (Account.md): the会計分類軸
         * every Budget Line and Journal Entry Line references. Scoped to
         * Organization, never Production/Project (Account.md "Production
         * Relationship" / "Project Relationship").
         */
        dbDelta("CREATE TABLE {$accounts} (
            id CHAR(36) NOT NULL,
            organization_id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            type VARCHAR(20) NOT NULL,
            code VARCHAR(50) NULL,
            parent_account_id CHAR(36) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY organization_id (organization_id)
        ) {$charsetCollate};");

        /*
         * Budget.md: Production-scoped plan Scenario. Version 1.0 keeps
         * at most one ACTIVE Budget per Production (enforced at the
         * Application layer, not a DB constraint, since DRAFT/ARCHIVED
         * Budgets for the same Production are expected to coexist -
         * Budget.md "Multiple Budgets").
         */
        dbDelta("CREATE TABLE {$budgets} (
            id CHAR(36) NOT NULL,
            production_id CHAR(36) NOT NULL,
            name VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            created_by CHAR(36) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_by CHAR(36) NOT NULL,
            updated_at DATETIME NOT NULL,
            activated_by CHAR(36) NULL,
            activated_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY production_id (production_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$budgetLines} (
            id CHAR(36) NOT NULL,
            budget_id CHAR(36) NOT NULL,
            account_id CHAR(36) NOT NULL,
            amount BIGINT NOT NULL,
            notes TEXT NULL,
            PRIMARY KEY  (id),
            KEY budget_id (budget_id)
        ) {$charsetCollate};");

        /*
         * JournalEntry.md: the Actual/Business-Fact-of-record. Always
         * Organization-scoped; production_id is nullable per Blueprint
         * ("可能な限りProductionとの関連を保持できる" - not every conceivable
         * entry need carry one, though every entry this Phase actually
         * generates, via Expense Confirmed, does).
         */
        dbDelta("CREATE TABLE {$journalEntries} (
            id CHAR(36) NOT NULL,
            organization_id CHAR(36) NOT NULL,
            production_id CHAR(36) NULL,
            journal_date DATETIME NOT NULL,
            description VARCHAR(500) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            source_event_type VARCHAR(50) NULL,
            source_event_id CHAR(36) NULL,
            reversal_of_journal_entry_id CHAR(36) NULL,
            created_by CHAR(36) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_by CHAR(36) NOT NULL,
            updated_at DATETIME NOT NULL,
            posted_by CHAR(36) NULL,
            posted_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY organization_id (organization_id),
            KEY production_id (production_id),
            KEY status (status)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$journalEntryLines} (
            id CHAR(36) NOT NULL,
            journal_entry_id CHAR(36) NOT NULL,
            account_id CHAR(36) NOT NULL,
            debit_credit VARCHAR(10) NOT NULL,
            amount BIGINT NOT NULL,
            description VARCHAR(500) NULL,
            PRIMARY KEY  (id),
            KEY journal_entry_id (journal_entry_id)
        ) {$charsetCollate};");

        /*
         * Expense.md: Aggregate Root for confirmed-support input side of
         * accounting, feeding Journal Entry generation on Confirm (see
         * ConfirmExpenseUseCase). Receipt (0..1) is not implemented this
         * Phase - no receipt_id column exists yet (disclosed Open Item).
         */
        dbDelta("CREATE TABLE {$expenses} (
            id CHAR(36) NOT NULL,
            production_id CHAR(36) NOT NULL,
            payer_person_id CHAR(36) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
            created_by CHAR(36) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_by CHAR(36) NOT NULL,
            updated_at DATETIME NOT NULL,
            confirmed_by CHAR(36) NULL,
            confirmed_at DATETIME NULL,
            PRIMARY KEY  (id),
            KEY production_id (production_id)
        ) {$charsetCollate};");

        dbDelta("CREATE TABLE {$expenseLines} (
            id CHAR(36) NOT NULL,
            expense_id CHAR(36) NOT NULL,
            account_id CHAR(36) NOT NULL,
            amount BIGINT NOT NULL,
            description VARCHAR(500) NULL,
            PRIMARY KEY  (id),
            KEY expense_id (expense_id)
        ) {$charsetCollate};");
    }
}
