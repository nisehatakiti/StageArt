<?php

declare(strict_types=1);

namespace StageArt\Accounting;

use DateTimeImmutable;
use StageArt\Domain\Expense\ExpenseReview;
use StageArt\Domain\Shared\Uuid;
use Throwable;

/**
 * Puts Expenses that LegacyExpenseMigration moved to POSTED back to the
 * CONFIRMED row they were (docs/handoffs/LegacyExpenseMigration-Runbook.md
 * "部分復旧"), run by an operator through
 * plugin/scripts/legacy_expense_migration_restore.php for an explicit
 * list of Expense ids.
 *
 * An Expense is restorable only while it is exactly what the migration
 * left: POSTED at revision 1 with the values the migration wrote, linked
 * to its own ExpenseConfirmed Journal Entry, with one history row - the
 * MIGRATED one - and lines still equal to that row's snapshot. Anything
 * done to it since (another approval step, a rejection, edited lines,
 * further history) makes it unrestorable, so a later change is never
 * silently undone.
 *
 * check() only reads. restore() writes nothing unless every listed
 * Expense passes check(); it then restores them all in one transaction,
 * checking each again once its row is locked, and rolls everything back
 * when any step fails. No Journal Entry is read for update or written.
 *
 * Inside the transaction, each Expense row is locked, then its history
 * and its lines, Expenses in id order. The history and lines are read
 * with locking reads so that, under REPEATABLE READ, the check sees
 * their current rows rather than the snapshot taken at the first
 * Expense; the application writes both only after updating the Expense
 * row (WordPressExpenseRepository::save()), which waits for the first
 * lock. The Journal Entry is read without a lock: the check uses only
 * its source event, which nothing changes after creation, and no
 * Journal Entry row is ever deleted.
 *
 * The history stays append-only (ExpenseApprovalWorkflow.md Version 1.8):
 * the MIGRATED row is kept and a MIGRATION_REVERTED row is added for the
 * same revision, in the same transaction as the Expense update, holding
 * the amounts and lines being undone, the operator's reason, the
 * operator's StageArt Person as actor and the time. An Expense with that
 * pair is never migrated again (LegacyExpenseMigrationClassifier).
 *
 * The actor is a LegacyExpenseMigrationOperator: a WordPress user holding
 * stageart_manage_accounts, linked to exactly one StageArt Person (the
 * WP-CLI script resolves it from --user); without one there is no call.
 * Each listed Expense is also recorded in the operations audit
 * (LegacyExpenseMigrationAudit, schema 1.45.0): SUCCEEDED in the same
 * transaction, so a failed audit insert rolls everything back; FAILED
 * after a rollback and REFUSED for a refused list, on their own.
 */
final class LegacyExpenseMigrationRestore
{
    public const OUTCOME_RESTORED = 'RESTORED';
    public const OUTCOME_REFUSED = 'REFUSED';
    public const OUTCOME_ROLLED_BACK = 'ROLLED_BACK';

    public const REFUSED_NO_IDS = 'NO_IDS';
    public const REFUSED_NOT_TRANSACTIONAL = LegacyExpenseMigration::REFUSED_NOT_TRANSACTIONAL;
    public const REFUSED_TARGET_MISMATCH = 'TARGET_MISMATCH';
    public const REFUSED_REASON_MISSING = 'REASON_MISSING';
    public const REFUSED_REASON_TOO_LONG = 'REASON_TOO_LONG';

    /** The longest restore reason accepted, in characters. */
    public const MAX_REASON_LENGTH = 500;

    public const NOT_FOUND = 'NOT_FOUND';
    public const STATE_CHANGED = 'STATE_CHANGED';
    public const MODIFIED_SINCE_MIGRATION = 'MODIFIED_SINCE_MIGRATION';
    public const HISTORY_MISMATCH = 'HISTORY_MISMATCH';
    public const LINES_CHANGED = 'LINES_CHANGED';
    public const JOURNAL_ENTRY_MISMATCH = 'JOURNAL_ENTRY_MISMATCH';

    public const FAILED_TRANSACTION_START = LegacyExpenseMigration::FAILED_TRANSACTION_START;
    public const FAILED_LOCK = LegacyExpenseMigration::FAILED_LOCK;
    public const FAILED_UPDATE = LegacyExpenseMigration::FAILED_UPDATE;
    public const FAILED_HISTORY_INSERT = LegacyExpenseMigration::FAILED_HISTORY_INSERT;
    public const FAILED_AUDIT_INSERT = LegacyExpenseMigration::FAILED_AUDIT_INSERT;

    /** The audit reason of the other listed Expenses when the list is refused because of some. */
    public const RUN_REFUSED = 'RUN_REFUSED';
    public const FAILED_COMMIT = LegacyExpenseMigration::FAILED_COMMIT;
    public const FAILED_UNEXPECTED = LegacyExpenseMigration::FAILED_UNEXPECTED;

    /**
     * Reads only. For each listed id: null when it can be restored, or why not.
     *
     * @param \wpdb $wpdb
     * @param string[] $ids
     * @return array<string, ?string>
     */
    public static function check($wpdb, array $ids): array
    {
        $t = LegacyExpenseMigration::tables($wpdb);
        $result = [];

        foreach (self::normalize($ids) as $id) {
            $expense = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['expenses']} WHERE id = %s", $id), ARRAY_A);
            $result[$id] = self::inspect($wpdb, $t, $id, is_array($expense) ? $expense : null, false)['reason'];
        }

        return $result;
    }

    /**
     * @param \wpdb $wpdb
     * @param string[] $ids
     * @param LegacyExpenseMigrationOperator $operator recorded as the history's actor and in the audit
     * @param string $reason why the migration is undone, stored in the history (never logged)
     * @return array{
     *     outcome: string,
     *     reason: ?string,
     *     run_id: string,
     *     expense_id: ?string,
     *     restored: string[],
     *     mismatches: array<string, string>,
     *     audit_failures: string[]
     * }
     */
    public static function restore($wpdb, array $ids, LegacyExpenseMigrationOperator $operator, string $reason): array
    {
        $ids = self::normalize($ids);
        $runId = LegacyExpenseMigrationAudit::newRunId();
        $summary = ['outcome' => self::OUTCOME_RESTORED, 'reason' => null, 'run_id' => $runId, 'expense_id' => null, 'restored' => [], 'mismatches' => [], 'audit_failures' => []];
        $reason = trim($reason);
        $audit = static fn (string $id, string $outcome, ?string $code): bool => LegacyExpenseMigrationAudit::record(
            $wpdb,
            $runId,
            LegacyExpenseMigrationAudit::OPERATION_REVERT,
            $id,
            $outcome,
            $code,
            $operator
        );

        // Argument and table problems are logged only: no target was looked at.
        if ($ids === []) {
            return self::refuse($summary, self::REFUSED_NO_IDS);
        }

        if ($reason === '') {
            return self::refuse($summary, self::REFUSED_REASON_MISSING);
        }

        if (mb_strlen($reason) > self::MAX_REASON_LENGTH) {
            return self::refuse($summary, self::REFUSED_REASON_TOO_LONG);
        }

        if (! LegacyExpenseMigration::isTransactional(LegacyExpenseMigration::storageEngines($wpdb))) {
            return self::refuse($summary, self::REFUSED_NOT_TRANSACTIONAL);
        }

        $mismatches = array_filter(self::check($wpdb, $ids), static fn (?string $reason): bool => $reason !== null);

        if ($mismatches !== []) {
            $summary['mismatches'] = $mismatches;

            foreach ($ids as $id) {
                if (! $audit($id, LegacyExpenseMigrationAudit::REFUSED, $mismatches[$id] ?? self::RUN_REFUSED)) {
                    $summary['audit_failures'][] = $id;
                }
            }

            return self::refuse($summary, self::REFUSED_TARGET_MISMATCH);
        }

        if ($wpdb->query('START TRANSACTION') === false) {
            return self::rolledBack($summary, $ids, null, self::FAILED_TRANSACTION_START, $audit);
        }

        $t = LegacyExpenseMigration::tables($wpdb);
        $current = null;
        $failure = null;
        $actedAt = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        // One lock order for every run, so two overlapping restores wait
        // for each other instead of deadlocking.
        $lockOrder = $ids;
        sort($lockOrder, SORT_STRING);

        try {
            foreach ($lockOrder as $id) {
                $current = $id;
                $failure = self::restoreOne($wpdb, $t, $id, $operator, $reason, $actedAt, $audit);

                if ($failure !== null) {
                    break;
                }
            }
        } catch (Throwable $exception) {
            $failure = LegacyExpenseMigration::isLockFailure($exception) ? self::FAILED_LOCK : self::FAILED_UNEXPECTED;
        }

        if ($failure === null && $wpdb->query('COMMIT') === false) {
            $current = null;
            $failure = self::FAILED_COMMIT;
        }

        if ($failure !== null) {
            $wpdb->query('ROLLBACK');

            return self::rolledBack($summary, $ids, $current, $failure, $audit);
        }

        error_log(sprintf(
            '%s restored to CONFIRMED: %s; recorded %s at %s; run=%s by%s',
            LegacyExpenseMigration::LOG_PREFIX,
            implode(', ', $ids),
            ExpenseReview::MIGRATION_REVERTED,
            $actedAt,
            $runId,
            $operator->describe()
        ));

        return array_merge($summary, ['restored' => $ids]);
    }

    /**
     * @param \wpdb $wpdb
     * @param array<string, string> $t
     * @param callable(string, string, ?string): bool $audit
     */
    private static function restoreOne($wpdb, array $t, string $id, LegacyExpenseMigrationOperator $operator, string $reason, string $actedAt, callable $audit): ?string
    {
        $expense = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['expenses']} WHERE id = %s FOR UPDATE", $id), ARRAY_A);
        LegacyExpenseMigration::assertLocked($wpdb);

        $found = self::inspect($wpdb, $t, $id, is_array($expense) ? $expense : null, true);

        if ($found['reason'] !== null) {
            return $found['reason'];
        }

        // The same conditions again in the WHERE: the row is restored only
        // as the migration left it.
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$t['expenses']} SET status = %s, revision = 0, submitted_by = NULL, submitted_at = NULL,
                requested_amount = NULL, approved_amount = NULL, approved_by = NULL, approved_at = NULL,
                posted_journal_entry_id = NULL
            WHERE id = %s AND status = %s AND revision = 1 AND posted_journal_entry_id = %s
                AND requested_amount = %d AND approved_amount = %d
                AND submitted_by = created_by AND submitted_at = created_at
                AND approved_by = confirmed_by AND approved_at = confirmed_at
                AND rejected_by IS NULL AND rejected_at IS NULL AND rejection_reason IS NULL",
            'CONFIRMED',
            $id,
            'POSTED',
            $found['journal_entry_id'],
            $found['amount'],
            $found['amount']
        ));

        if ($updated !== 1) {
            return self::FAILED_UPDATE;
        }

        // Appended next to the MIGRATED row, never replacing it. A plain
        // INSERT: the unique (expense_id, revision, action) key makes a
        // second revert of the same migration an error.
        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$t['reviews']}
                (id, expense_id, revision, action, requested_amount, approved_amount, lines_snapshot, reason, actor_person_id, acted_at)
            VALUES (%s, %s, %d, %s, %d, %d, %s, %s, %s, %s)",
            Uuid::generate(),
            $id,
            1,
            ExpenseReview::MIGRATION_REVERTED,
            $found['amount'],
            $found['amount'],
            (string) json_encode($found['lines'], JSON_UNESCAPED_UNICODE),
            $reason,
            $operator->personId(),
            $actedAt
        ));

        if ($inserted !== 1) {
            return self::FAILED_HISTORY_INSERT;
        }

        // In the same transaction: no revert commits without its audit row.
        return $audit($id, LegacyExpenseMigrationAudit::SUCCEEDED, null) ? null : self::FAILED_AUDIT_INSERT;
    }

    /**
     * Whether `$expense` is still exactly what LegacyExpenseMigration left.
     *
     * @param \wpdb $wpdb
     * @param array<string, string> $t
     * @param array<string, mixed>|null $expense
     * @return array{reason: ?string, journal_entry_id: ?string, amount: ?int, lines: array<int, array{account_id: string, amount: int, description: ?string}>}
     */
    private static function inspect($wpdb, array $t, string $id, ?array $expense, bool $lock): array
    {
        $no = static fn (string $reason): array => ['reason' => $reason, 'journal_entry_id' => null, 'amount' => null, 'lines' => []];

        if ($expense === null) {
            return $no(self::NOT_FOUND);
        }

        if (
            ($expense['status'] ?? null) !== 'POSTED'
            || (int) ($expense['revision'] ?? -1) !== 1
            || (string) ($expense['posted_journal_entry_id'] ?? '') === ''
            || ($expense['rejected_by'] ?? null) !== null
            || ($expense['rejected_at'] ?? null) !== null
            || ($expense['rejection_reason'] ?? null) !== null
        ) {
            return $no(self::STATE_CHANGED);
        }

        if (
            ($expense['requested_amount'] ?? null) === null
            || (int) $expense['requested_amount'] !== (int) ($expense['approved_amount'] ?? -1)
            || ! self::same($expense['submitted_by'] ?? null, $expense['created_by'] ?? null)
            || ! self::same($expense['submitted_at'] ?? null, $expense['created_at'] ?? null)
            || ! self::same($expense['approved_by'] ?? null, $expense['confirmed_by'] ?? null)
            || ! self::same($expense['approved_at'] ?? null, $expense['confirmed_at'] ?? null)
        ) {
            return $no(self::MODIFIED_SINCE_MIGRATION);
        }

        $amount = (int) $expense['requested_amount'];
        $forUpdate = $lock ? ' FOR UPDATE' : '';
        $history = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$t['reviews']} WHERE expense_id = %s{$forUpdate}", $id),
            ARRAY_A
        ) ?: [];

        if ($lock) {
            LegacyExpenseMigration::assertLocked($wpdb);
        }

        $migrated = $history[0] ?? null;

        if (
            count($history) !== 1
            || ($migrated['action'] ?? null) !== 'MIGRATED'
            || (int) ($migrated['revision'] ?? -1) !== 1
            || (int) ($migrated['requested_amount'] ?? -1) !== $amount
            || (int) ($migrated['approved_amount'] ?? -1) !== $amount
            || ! self::same($migrated['actor_person_id'] ?? null, $expense['confirmed_by'] ?? null)
        ) {
            return $no(self::HISTORY_MISMATCH);
        }

        $lines = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['lines']} WHERE expense_id = %s{$forUpdate}", $id), ARRAY_A) ?: [];

        if ($lock) {
            LegacyExpenseMigration::assertLocked($wpdb);
        }
        $snapshot = json_decode((string) ($migrated['lines_snapshot'] ?? ''), true);
        $current = array_map(
            static fn (array $line): array => [
                'account_id' => (string) $line['account_id'],
                'amount' => (int) $line['amount'],
                'description' => $line['description'] ?? null,
            ],
            $lines
        );

        if (
            ! is_array($snapshot)
            || self::sorted($current) !== self::sorted($snapshot)
            || array_sum(array_column($current, 'amount')) !== $amount
        ) {
            return $no(self::LINES_CHANGED);
        }

        $journalEntryId = (string) $expense['posted_journal_entry_id'];
        $entry = $wpdb->get_row(
            $wpdb->prepare("SELECT id, source_event_type, source_event_id FROM {$t['journal_entries']} WHERE id = %s", $journalEntryId),
            ARRAY_A
        );

        if (! is_array($entry) || $entry['source_event_type'] !== 'ExpenseConfirmed' || (string) $entry['source_event_id'] !== $id) {
            return $no(self::JOURNAL_ENTRY_MISMATCH);
        }

        return ['reason' => null, 'journal_entry_id' => $journalEntryId, 'amount' => $amount, 'lines' => $current];
    }

    /**
     * @param mixed $a
     * @param mixed $b
     */
    private static function same($a, $b): bool
    {
        return $a !== null && $b !== null && (string) $a === (string) $b;
    }

    /**
     * @param array<int, mixed> $lines
     * @return array<int, mixed>
     */
    private static function sorted(array $lines): array
    {
        $lines = array_map(
            static fn ($line): array => is_array($line)
                ? ['account_id' => (string) ($line['account_id'] ?? ''), 'amount' => (int) ($line['amount'] ?? 0), 'description' => $line['description'] ?? null]
                : [],
            array_values($lines)
        );
        usort($lines, static fn (array $a, array $b): int => [$a['account_id'] ?? '', $a['amount'] ?? 0, (string) ($a['description'] ?? '')]
            <=> [$b['account_id'] ?? '', $b['amount'] ?? 0, (string) ($b['description'] ?? '')]);

        return $lines;
    }

    /**
     * @param string[] $ids
     * @return string[] distinct, non-empty, in the order given
     */
    private static function normalize(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map(static fn ($id): string => trim((string) $id), $ids), static fn (string $id): bool => $id !== '')));
    }

    /**
     * @param array{outcome: string, reason: ?string, expense_id: ?string, restored: string[], mismatches: array<string, string>} $summary
     * @return array{outcome: string, reason: ?string, expense_id: ?string, restored: string[], mismatches: array<string, string>}
     */
    private static function refuse(array $summary, string $reason): array
    {
        $pairs = [];

        foreach ($summary['mismatches'] as $id => $mismatch) {
            $pairs[] = "{$id}={$mismatch}";
        }

        error_log(LegacyExpenseMigration::LOG_PREFIX . " restore refused: {$reason}" . ($pairs === [] ? '' : ' (' . implode(', ', $pairs) . ')') . '; no Expense was changed; run=' . $summary['run_id']);

        return array_merge($summary, ['outcome' => self::OUTCOME_REFUSED, 'reason' => $reason]);
    }

    /**
     * Logs the rollback and records one FAILED audit row per listed
     * Expense - its own reason for the one that failed, RUN_ROLLED_BACK for
     * the others (the run's reason for all when no single Expense failed).
     *
     * @param array<string, mixed> $summary
     * @param string[] $ids
     * @param callable(string, string, ?string): bool $audit
     * @return array<string, mixed>
     */
    private static function rolledBack(array $summary, array $ids, ?string $id, string $reason, callable $audit): array
    {
        error_log(LegacyExpenseMigration::LOG_PREFIX . ' restore rolled back: ' . ($id === null ? $reason : "{$id}={$reason}") . "; nothing was restored; run={$summary['run_id']}");

        foreach ($ids as $listed) {
            $code = $id === null || $listed === $id ? $reason : LegacyExpenseMigrationAudit::RUN_ROLLED_BACK;

            if (! $audit($listed, LegacyExpenseMigrationAudit::FAILED, $code)) {
                $summary['audit_failures'][] = $listed;
                error_log(LegacyExpenseMigration::LOG_PREFIX . " audit record failed: {$listed} FAILED");
            }
        }

        return array_merge($summary, ['outcome' => self::OUTCOME_ROLLED_BACK, 'reason' => $reason, 'expense_id' => $id]);
    }
}
