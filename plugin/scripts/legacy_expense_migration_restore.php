<?php
/**
 * StageArt schema 1.44.0: undoes the legacy Expense migration for the
 * listed Expense ids (the runbook's partial restore). The Expense goes
 * back to CONFIRMED and a MIGRATION_REVERTED history entry is added next
 * to the MIGRATED one - no history is deleted - after which the Expense
 * is never migrated again.
 *
 * Check (reads only, writes nothing):
 *   wp eval-file plugin/scripts/legacy_expense_migration_restore.php check <id>[,<id>...] --path=<wordpress-root>
 *
 * Restore (only after the check was reviewed, a backup taken and the
 * restore approved), run as the operator's own WordPress user:
 *   wp --user=<operator-login> eval-file plugin/scripts/legacy_expense_migration_restore.php restore <id>[,<id>...] "<reason>" --path=<wordpress-root>
 *
 * The operator is recorded as the StageArt Person linked to that
 * WordPress user; without --user, or without a linked Person, nothing is
 * written. The OS account running WP-CLI and the WordPress user id go to
 * the output and the log only. Restore writes nothing unless every listed
 * Expense is still exactly as the migration left it, and restores them all
 * or none. No Journal Entry is changed. Output and logs carry ids, reason
 * codes and those operator identifiers only - never the reason text.
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
    WP_CLI::error('Use: check <id>[,<id>...] or restore <id>[,<id>...] "<reason>" (restore with --user=<operator-login>)');

    return;
}

$stageartSchema = (string) get_option('stageart_db_schema_version', '');

if (version_compare($stageartSchema, '1.44.0', '<')) {
    WP_CLI::error("The database schema is {$stageartSchema}; there is no migration to restore before schema 1.44.0.");

    return;
}

$stageartReason = trim((string) ($stageartArgs[2] ?? ''));
$stageartActor = null;
$stageartWpUser = 0;

if ($stageartMode === 'restore') {
    if ($stageartReason === '') {
        WP_CLI::error('restore needs the reason as its third argument (stored in the history; do not include personal information).');

        return;
    }

    // Who the operator is, as StageArt knows them: the Person linked to
    // the WordPress user WP-CLI runs as (--user). Nothing stands in for
    // it when there is none.
    $stageartWpUser = (int) get_current_user_id();

    if ($stageartWpUser <= 0) {
        WP_CLI::error('restore must run as the operator\'s WordPress user: add --user=<operator-login>. Nothing was written.');

        return;
    }

    $stageartActor = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}stageart_people WHERE wp_user_id = %d",
        $stageartWpUser
    ));

    if ($stageartActor === null || (string) $stageartActor === '') {
        WP_CLI::error("WordPress user {$stageartWpUser} is not linked to a StageArt Person; the restore cannot record its actor. Nothing was written.");

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

// The OS account running WP-CLI, for the log: the effective user when the
// posix extension is there, otherwise the environment (which the caller
// controls), otherwise "unknown".
$stageartOsUser = null;

if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
    $stageartPw = posix_getpwuid(posix_geteuid());
    $stageartOsUser = is_array($stageartPw) ? (string) ($stageartPw['name'] ?? '') : null;
}

if ($stageartOsUser === null || $stageartOsUser === '') {
    $stageartEnvUser = getenv('USER') ?: getenv('USERNAME');
    $stageartOsUser = $stageartEnvUser === false || $stageartEnvUser === '' ? null : 'env:' . $stageartEnvUser;
}

WP_CLI::line("Operator: person={$stageartActor} wp_user={$stageartWpUser} os_user=" . ($stageartOsUser ?? 'unknown'));

$stageartResult = LegacyExpenseMigrationRestore::restore(
    $wpdb,
    $stageartIds,
    (string) $stageartActor,
    $stageartReason,
    ['wp_user' => $stageartWpUser, 'os_user' => $stageartOsUser]
);

if ($stageartResult['outcome'] === LegacyExpenseMigrationRestore::OUTCOME_REFUSED) {
    foreach ($stageartResult['mismatches'] as $stageartId => $stageartMismatch) {
        WP_CLI::warning("Not restorable: {$stageartId}={$stageartMismatch}");
    }

    WP_CLI::error("Refused ({$stageartResult['reason']}) - nothing was written.");

    return;
}

if ($stageartResult['outcome'] === LegacyExpenseMigrationRestore::OUTCOME_ROLLED_BACK) {
    $stageartAt = $stageartResult['expense_id'] === null ? '' : " at {$stageartResult['expense_id']}";
    WP_CLI::error("Rolled back ({$stageartResult['reason']}{$stageartAt}) - nothing was restored.");

    return;
}

WP_CLI::success('Restored to CONFIRMED, MIGRATION_REVERTED recorded: ' . count($stageartResult['restored']));
