<?php
/**
 * StageArt schema 1.44.0: the pre-workflow CONFIRMED Expense migration,
 * run by an operator - never by a deploy or a web request.
 *
 * Preview (reads only, writes nothing):
 *   wp eval-file plugin/scripts/legacy_expense_migration.php --path=<wordpress-root>
 *
 * Execute (only after the preview was reviewed, a backup taken and the
 * run approved; <fingerprint> is the one the preview printed):
 *   wp eval-file plugin/scripts/legacy_expense_migration.php execute <fingerprint> --path=<wordpress-root>
 *
 * Execute writes nothing unless the plan it finds is still the one the
 * fingerprint names and the written tables are InnoDB. Output and logs
 * carry Expense / Journal Entry ids, counts and reason codes only.
 * See docs/handoffs/LegacyExpenseMigration-Runbook.md.
 */

use StageArt\Accounting\LegacyExpenseMigration;

if (! defined('WP_CLI') || ! WP_CLI) {
    echo "Run this through WP-CLI (wp eval-file).\n";

    return;
}

global $wpdb;

$stageartArgs = isset($args) && is_array($args) ? array_values($args) : [];
$stageartMode = $stageartArgs[0] ?? 'preview';

if (! in_array($stageartMode, ['preview', 'execute'], true)) {
    WP_CLI::error('Unknown mode. Use no argument to preview, or: execute <fingerprint>');

    return;
}

$stageartSchema = (string) get_option('stageart_db_schema_version', '');

if (version_compare($stageartSchema, '1.44.0', '<')) {
    WP_CLI::error("The database schema is {$stageartSchema}; schema 1.44.0 must be installed before the Expense migration.");

    return;
}

$stageartPlan = LegacyExpenseMigration::preview($wpdb);

WP_CLI::line('CONFIRMED Expenses: ' . $stageartPlan['confirmed']);
WP_CLI::line('Candidates to migrate: ' . count($stageartPlan['candidates']));
foreach ($stageartPlan['candidates'] as $stageartId => $stageartCandidate) {
    WP_CLI::line("  {$stageartId} journal_entry={$stageartCandidate['journal_entry_id']}");
}
WP_CLI::line('Left for manual review: ' . count($stageartPlan['manual_review']));
foreach ($stageartPlan['manual_review'] as $stageartId => $stageartReason) {
    WP_CLI::line("  {$stageartId}={$stageartReason}");
}
foreach ($stageartPlan['storage_engines'] as $stageartTable => $stageartEngine) {
    WP_CLI::line("Storage engine {$stageartTable}: " . ($stageartEngine ?? 'unknown'));
}
WP_CLI::line('Fingerprint: ' . $stageartPlan['fingerprint']);

if ($stageartMode === 'preview') {
    WP_CLI::success('Preview only - nothing was written.');

    return;
}

$stageartFingerprint = (string) ($stageartArgs[1] ?? '');

if ($stageartFingerprint === '') {
    WP_CLI::error('execute needs the fingerprint printed by the preview.');

    return;
}

$stageartResult = LegacyExpenseMigration::execute($wpdb, $stageartFingerprint);

if ($stageartResult['outcome'] === LegacyExpenseMigration::OUTCOME_REFUSED) {
    WP_CLI::error("Refused ({$stageartResult['refused_reason']}) - nothing was written.");

    return;
}

WP_CLI::line('Migrated: ' . count($stageartResult['migrated']));
foreach ($stageartResult['failed'] as $stageartId => $stageartReason) {
    WP_CLI::warning("Rolled back, still CONFIRMED: {$stageartId}={$stageartReason}");
}

if ($stageartResult['failed'] !== []) {
    WP_CLI::error(count($stageartResult['failed']) . ' Expense(s) failed and were rolled back.');

    return;
}

WP_CLI::success('Migration finished.');
