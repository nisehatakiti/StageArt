<?php
/**
 * StageArt schema 1.44.0: puts Expenses the legacy Expense migration moved
 * to POSTED back to CONFIRMED (the runbook's partial restore), for the
 * listed Expense ids only.
 *
 * Check (reads only, writes nothing):
 *   wp eval-file plugin/scripts/legacy_expense_migration_restore.php check <id>[,<id>...] --path=<wordpress-root>
 *
 * Restore (only after the check was reviewed, a backup taken and the
 * restore approved):
 *   wp eval-file plugin/scripts/legacy_expense_migration_restore.php restore <id>[,<id>...] --path=<wordpress-root>
 *
 * Restore writes nothing unless every listed Expense is still exactly as
 * the migration left it, and restores them all or none. No Journal Entry
 * is changed. Output and logs carry Expense ids and reason codes only.
 * See docs/handoffs/LegacyExpenseMigration-Runbook.md.
 */

use StageArt\Accounting\LegacyExpenseMigrationRestore;

if (! defined('WP_CLI') || ! WP_CLI) {
    echo "Run this through WP-CLI (wp eval-file).\n";

    return;
}

global $wpdb;

$stageartArgs = isset($args) && is_array($args) ? array_values($args) : [];
$stageartMode = $stageartArgs[0] ?? '';
$stageartIds = array_values(array_filter(array_map('trim', explode(',', (string) ($stageartArgs[1] ?? ''))), static fn (string $id): bool => $id !== ''));

if (! in_array($stageartMode, ['check', 'restore'], true) || $stageartIds === []) {
    WP_CLI::error('Use: check <id>[,<id>...] or restore <id>[,<id>...]');

    return;
}

$stageartSchema = (string) get_option('stageart_db_schema_version', '');

if (version_compare($stageartSchema, '1.44.0', '<')) {
    WP_CLI::error("The database schema is {$stageartSchema}; there is no migration to restore before schema 1.44.0.");

    return;
}

$stageartCheck = LegacyExpenseMigrationRestore::check($wpdb, $stageartIds);
$stageartBlocked = 0;

foreach ($stageartCheck as $stageartId => $stageartReason) {
    WP_CLI::line("  {$stageartId}=" . ($stageartReason ?? 'RESTORABLE'));
    $stageartBlocked += $stageartReason === null ? 0 : 1;
}

if ($stageartMode === 'check') {
    if ($stageartBlocked > 0) {
        WP_CLI::warning("{$stageartBlocked} Expense(s) cannot be restored; a restore of this list would be refused.");
    }

    WP_CLI::success('Check only - nothing was written.');

    return;
}

$stageartResult = LegacyExpenseMigrationRestore::restore($wpdb, $stageartIds);

if ($stageartResult['outcome'] === LegacyExpenseMigrationRestore::OUTCOME_REFUSED) {
    WP_CLI::error("Refused ({$stageartResult['reason']}) - nothing was written.");

    return;
}

if ($stageartResult['outcome'] === LegacyExpenseMigrationRestore::OUTCOME_ROLLED_BACK) {
    $stageartAt = $stageartResult['expense_id'] === null ? '' : " at {$stageartResult['expense_id']}";
    WP_CLI::error("Rolled back ({$stageartResult['reason']}{$stageartAt}) - nothing was restored.");

    return;
}

WP_CLI::success('Restored to CONFIRMED: ' . count($stageartResult['restored']));
