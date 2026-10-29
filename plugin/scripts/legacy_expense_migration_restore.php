<?php
/**
 * StageArt: undoes the legacy Expense migration for the listed Expense
 * ids (the runbook's partial restore). The Expense goes back to CONFIRMED
 * and a MIGRATION_REVERTED history entry is added next to the MIGRATED
 * one - no history is deleted - after which the Expense is never
 * migrated again. Each restored Expense also gets an operations audit row
 * (schema 1.45.0) in the same transaction.
 *
 * Check (reads only, writes nothing):
 *   wp eval-file plugin/scripts/legacy_expense_migration_restore.php check <id>[,<id>...] --path=<wordpress-root>
 *
 * Restore (only after the check was reviewed, a backup taken and the
 * restore approved), as the operator's own WordPress user, which must hold
 * stageart_manage_accounts and be linked to one StageArt Person:
 *   wp --user=<operator-login> eval-file plugin/scripts/legacy_expense_migration_restore.php restore <id>[,<id>...] "<reason>" --path=<wordpress-root>
 *
 * --user names the user; it does not authenticate it. Without a
 * qualifying operator nothing is written - no other id stands in for it.
 * Restore changes no Expense unless every listed Expense is still exactly
 * as the migration left it, and restores them all or none. No Journal
 * Entry is changed. Output and logs carry ids, reason codes and the
 * operator's identifiers only - never the reason text.
 * See docs/handoffs/LegacyExpenseMigration-Runbook.md.
 */

use StageArt\Accounting\LegacyExpenseMigrationOperator;
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
    WP_CLI::error('Use: check <id>[,<id>...] or restore <id>[,<id>...] "<reason>" (restore with --user=<operator-login>)');

    return;
}

$stageartSchema = (string) get_option('stageart_db_schema_version', '');

if (version_compare($stageartSchema, '1.45.0', '<')) {
    WP_CLI::error("The database schema is {$stageartSchema}; schema 1.45.0 must be installed before restoring the Expense migration.");

    return;
}

$stageartReason = trim((string) ($stageartArgs[2] ?? ''));
$stageartOperator = null;

if ($stageartMode === 'restore') {
    if ($stageartReason === '') {
        WP_CLI::error('restore needs the reason as its third argument (stored in the history; do not include personal information).');

        return;
    }

    $stageartOperator = LegacyExpenseMigrationOperator::fromCurrentWordPressUser($wpdb, LegacyExpenseMigrationOperator::detectOsUser());

    if (! $stageartOperator instanceof LegacyExpenseMigrationOperator) {
        WP_CLI::error("Refused ({$stageartOperator}): restore must run as a WordPress user holding " . LegacyExpenseMigrationOperator::CAPABILITY . ' and linked to one StageArt Person (--user=<operator-login>). Nothing was written.');

        return;
    }
}

$stageartCheck = LegacyExpenseMigrationRestore::check($wpdb, $stageartIds);
$stageartBlocked = 0;

foreach ($stageartCheck as $stageartId => $stageartCheckReason) {
    WP_CLI::line("  {$stageartId}=" . ($stageartCheckReason ?? 'RESTORABLE'));
    $stageartBlocked += $stageartCheckReason === null ? 0 : 1;
}

if ($stageartMode === 'check') {
    if ($stageartBlocked > 0) {
        WP_CLI::warning("{$stageartBlocked} Expense(s) cannot be restored; a restore of this list would be refused.");
    }

    WP_CLI::success('Check only - nothing was written.');

    return;
}

WP_CLI::line('Operator:' . $stageartOperator->describe());

$stageartResult = LegacyExpenseMigrationRestore::restore($wpdb, $stageartIds, $stageartOperator, $stageartReason);

WP_CLI::line("Run: {$stageartResult['run_id']}");

foreach ($stageartResult['audit_failures'] as $stageartId) {
    WP_CLI::warning("The audit row could not be written: {$stageartId}");
}

if ($stageartResult['outcome'] === LegacyExpenseMigrationRestore::OUTCOME_REFUSED) {
    foreach ($stageartResult['mismatches'] as $stageartId => $stageartMismatch) {
        WP_CLI::warning("Not restorable: {$stageartId}={$stageartMismatch}");
    }

    WP_CLI::error("Refused ({$stageartResult['reason']}) - no Expense was changed.");

    return;
}

if ($stageartResult['outcome'] === LegacyExpenseMigrationRestore::OUTCOME_ROLLED_BACK) {
    $stageartAt = $stageartResult['expense_id'] === null ? '' : " at {$stageartResult['expense_id']}";
    WP_CLI::error("Rolled back ({$stageartResult['reason']}{$stageartAt}) - nothing was restored.");

    return;
}

if ($stageartResult['audit_failures'] !== []) {
    WP_CLI::error('Restored, but ' . count($stageartResult['audit_failures']) . ' audit row(s) could not be written.');

    return;
}

WP_CLI::success('Restored to CONFIRMED, MIGRATION_REVERTED recorded: ' . count($stageartResult['restored']));
