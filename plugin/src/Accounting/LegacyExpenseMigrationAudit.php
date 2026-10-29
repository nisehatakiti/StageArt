<?php

declare(strict_types=1);

namespace StageArt\Accounting;

use StageArt\Domain\Shared\Uuid;

/**
 * The operations audit of the legacy Expense migration and its revert
 * (schema 1.45.0, ExpenseApprovalWorkflow.md Version 1.9), kept apart
 * from the Expense's business history (stageart_expense_reviews).
 *
 * Rows are only ever inserted - nothing in StageArt updates or deletes
 * them. One row per Expense and outcome:
 *
 * - SUCCEEDED is inserted inside the Expense's own transaction, next to
 *   the status change and the history row, so either all three commit or
 *   none does. The unique (operation, success_key) key - success_key is
 *   the Expense id on SUCCEEDED rows and NULL otherwise - allows one
 *   successful migration and one successful revert per Expense.
 * - FAILED (rolled back) and REFUSED (nothing attempted) are inserted on
 *   their own, after the ROLLBACK or instead of the transaction: they
 *   cannot share a transaction that is being undone. When such a row
 *   cannot be written, the failure is still logged and reported, and the
 *   run ends in an error.
 *
 * Every row carries the run it belongs to, the operator (WordPress user,
 * StageArt Person, OS account when known) and the time in UTC. A run
 * refused before an operator is established writes nothing here.
 *
 * This is an audit trail kept by the application, not tamper-proof
 * storage: anyone with direct database access can change it.
 */
final class LegacyExpenseMigrationAudit
{
    public const TABLE = 'stageart_legacy_expense_migration_audit';

    public const OPERATION_MIGRATION = 'LEGACY_EXPENSE_MIGRATION';
    public const OPERATION_REVERT = 'LEGACY_EXPENSE_MIGRATION_REVERT';

    public const SUCCEEDED = 'SUCCEEDED';
    public const FAILED = 'FAILED';
    public const REFUSED = 'REFUSED';

    /** The reason given to the other Expenses of a revert run rolled back because of one of them. */
    public const RUN_ROLLED_BACK = 'RUN_ROLLED_BACK';

    public static function newRunId(): string
    {
        return Uuid::generate();
    }

    /**
     * Inserts one row; true when exactly one row was inserted.
     *
     * @param \wpdb $wpdb
     * @param string|null $fingerprint the plan fingerprint a migration run was given
     */
    public static function record(
        $wpdb,
        string $runId,
        string $operation,
        ?string $expenseId,
        string $outcome,
        ?string $reasonCode,
        LegacyExpenseMigrationOperator $operator,
        ?string $fingerprint = null
    ): bool {
        $columns = [
            'id' => Uuid::generate(),
            'run_id' => $runId,
            'operation' => $operation,
            'expense_id' => $expenseId,
            'outcome' => $outcome,
            'reason_code' => $reasonCode,
            'success_key' => $outcome === self::SUCCEEDED ? $expenseId : null,
            'plan_fingerprint' => $fingerprint === null ? null : (substr((string) preg_replace('/[^0-9a-f]/', '', strtolower($fingerprint)), 0, 16) ?: null),
            'wp_user_id' => $operator->wpUserId(),
            'person_id' => $operator->personId(),
            'os_user' => $operator->osUser(),
            'acted_at_utc' => gmdate('Y-m-d H:i:s'),
        ];

        $placeholders = [];
        $args = [];

        // $wpdb->prepare() has no NULL placeholder: NULLs go in literally.
        foreach ($columns as $value) {
            if ($value === null) {
                $placeholders[] = 'NULL';
            } elseif (is_int($value)) {
                $placeholders[] = '%d';
                $args[] = $value;
            } else {
                $placeholders[] = '%s';
                $args[] = $value;
            }
        }

        $table = $wpdb->prefix . self::TABLE;
        $sql = "INSERT INTO {$table} (" . implode(', ', array_keys($columns)) . ') VALUES (' . implode(', ', $placeholders) . ')';

        return $wpdb->query($wpdb->prepare($sql, ...$args)) === 1;
    }
}
