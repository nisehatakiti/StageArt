<?php
/**
 * StageArt: the pre-workflow CONFIRMED Expense migration (schema 1.44.0
 * columns, 1.45.0 audit), run by an operator - never by a deploy or a web
 * request.
 *
 * Preview (reads only, writes nothing):
 *   wp eval-file plugin/scripts/legacy_expense_migration.php --path=<wordpress-root>
 *
 * Execute (only after the preview was reviewed, a backup taken and the
 * run approved; <fingerprint> is the one the preview printed), as the
 * operator's own WordPress user, which must hold stageart_manage_accounts
 * and be linked to one StageArt Person:
 *   wp --user=<operator-login> eval-file plugin/scripts/legacy_expense_migration.php execute <fingerprint> --path=<wordpress-root>
 *
 * --user names the user; it does not authenticate it. Execute writes
 * nothing unless the operator qualifies, the plan it finds is still the
 * one the fingerprint names and the written tables are InnoDB. Each
 * migrated Expense gets an audit row in the same transaction. Output and
 * logs carry Expense / Journal Entry ids, counts, reason codes and the
 * operator's identifiers only.
 * See docs/handoffs/LegacyExpenseMigration-Runbook.md.
 */

use StageArt\Accounting\LegacyExpenseMigration;
use StageArt\Accounting\LegacyExpenseMigrationOperator;

if (! defined('WP_CLI') || ! WP_CLI) {
    echo "Run this through WP-CLI (wp eval-file).\n";

    return;
}

global $wpdb;

$stageartArgs = isset($args) && is_array($args) ? array_values($args) : [];
$stageartMode = $stageartArgs[0] ?? 'preview';

if (! in_array($stageartMode, ['preview', 'execute'], true)) {
    WP_CLI::error('Unknown mode. Use no argument to preview, or: execute <fingerprint> (with --user=<operator-login>)');

    return;
}

$stageartSchema = (string) get_option('stageart_db_schema_version', '');

if (version_compare($stageartSchema, '1.45.0', '<')) {
    WP_CLI::error("The database schema is {$stageartSchema}; schema 1.45.0 must be installed before the Expense migration.");

    return;
}

$stageartFingerprint = (string) ($stageartArgs[1] ?? '');
$stageartOperator = null;

if ($stageartMode === 'execute') {
    if ($stageartFingerprint === '') {
        WP_CLI::error('execute needs the fingerprint printed by the preview.');

        return;
    }

    $stageartOperator = LegacyExpenseMigrationOperator::fromCurrentWordPressUser($wpdb, LegacyExpenseMigrationOperator::detectOsUser());

    if (! $stageartOperator instanceof LegacyExpenseMigrationOperator) {
        WP_CLI::error("Refused ({$stageartOperator}): execute must run as a WordPress user holding " . LegacyExpenseMigrationOperator::CAPABILITY . ' and linked to one StageArt Person (--user=<operator-login>). Nothing was written.');

        return;
    }
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

WP_CLI::line('Operator:' . $stageartOperator->describe());

$stageartResult = LegacyExpenseMigration::execute($wpdb, $stageartFingerprint, $stageartOperator);

WP_CLI::line("Run: {$stageartResult['run_id']}");

foreach ($stageartResult['audit_failures'] as $stageartId) {
    WP_CLI::warning("The audit row could not be written: {$stageartId}");
}

if ($stageartResult['outcome'] === LegacyExpenseMigration::OUTCOME_REFUSED) {
    WP_CLI::error("Refused ({$stageartResult['refused_reason']}) - no Expense was changed.");

    return;
}

WP_CLI::line('Migrated: ' . count($stageartResult['migrated']));
foreach ($stageartResult['failed'] as $stageartId => $stageartReason) {
    WP_CLI::warning("Rolled back, still CONFIRMED: {$stageartId}={$stageartReason}");
}

if ($stageartResult['failed'] !== [] || $stageartResult['audit_failures'] !== []) {
    WP_CLI::error(count($stageartResult['failed']) . ' Expense(s) failed and were rolled back; ' . count($stageartResult['audit_failures']) . ' audit row(s) could not be written.');

    return;
}

WP_CLI::success('Migration finished.');
