<?php

declare(strict_types=1);

namespace StageArt\Accounting;

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
 */
final class LegacyExpenseMigrationRestore
{
    public const OUTCOME_RESTORED = 'RESTORED';
    public const OUTCOME_REFUSED = 'REFUSED';
    public const OUTCOME_ROLLED_BACK = 'ROLLED_BACK';

    public const REFUSED_NO_IDS = 'NO_IDS';
    public const REFUSED_NOT_TRANSACTIONAL = LegacyExpenseMigration::REFUSED_NOT_TRANSACTIONAL;
    public const REFUSED_TARGET_MISMATCH = 'TARGET_MISMATCH';

    public const NOT_FOUND = 'NOT_FOUND';
    public const STATE_CHANGED = 'STATE_CHANGED';
    public const MODIFIED_SINCE_MIGRATION = 'MODIFIED_SINCE_MIGRATION';
    public const HISTORY_MISMATCH = 'HISTORY_MISMATCH';
    public const LINES_CHANGED = 'LINES_CHANGED';
    public const JOURNAL_ENTRY_MISMATCH = 'JOURNAL_ENTRY_MISMATCH';

    public const FAILED_TRANSACTION_START = LegacyExpenseMigration::FAILED_TRANSACTION_START;
    public const FAILED_LOCK = LegacyExpenseMigration::FAILED_LOCK;
    public const FAILED_UPDATE = LegacyExpenseMigration::FAILED_UPDATE;
    public const FAILED_HISTORY_DELETE = 'HISTORY_DELETE_FAILED';
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
     * @return array{
     *     outcome: string,
     *     reason: ?string,
     *     expense_id: ?string,
     *     restored: string[],
     *     mismatches: array<string, string>
     * }
     */
    public static function restore($wpdb, array $ids): array
    {
        $ids = self::normalize($ids);
        $summary = ['outcome' => self::OUTCOME_RESTORED, 'reason' => null, 'expense_id' => null, 'restored' => [], 'mismatches' => []];

        if ($ids === []) {
            return self::refuse($summary, self::REFUSED_NO_IDS);
        }

        if (! LegacyExpenseMigration::isTransactional(LegacyExpenseMigration::storageEngines($wpdb))) {
            return self::refuse($summary, self::REFUSED_NOT_TRANSACTIONAL);
        }

        $mismatches = array_filter(self::check($wpdb, $ids), static fn (?string $reason): bool => $reason !== null);

        if ($mismatches !== []) {
            $summary['mismatches'] = $mismatches;

            return self::refuse($summary, self::REFUSED_TARGET_MISMATCH);
        }

        if ($wpdb->query('START TRANSACTION') === false) {
            return self::rolledBack($summary, null, self::FAILED_TRANSACTION_START);
        }

        $t = LegacyExpenseMigration::tables($wpdb);
        $current = null;
        $failure = null;

        try {
            foreach ($ids as $id) {
                $current = $id;
                $failure = self::restoreOne($wpdb, $t, $id);

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

            return self::rolledBack($summary, $current, $failure);
        }

        error_log(LegacyExpenseMigration::LOG_PREFIX . ' restored to CONFIRMED: ' . implode(', ', $ids));

        return array_merge($summary, ['restored' => $ids]);
    }

    /**
     * @param \wpdb $wpdb
     * @param array<string, string> $t
     */
    private static function restoreOne($wpdb, array $t, string $id): ?string
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

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$t['reviews']} WHERE id = %s AND expense_id = %s AND action = %s AND revision = 1",
            $found['review_id'],
            $id,
            'MIGRATED'
        ));

        return $deleted === 1 ? null : self::FAILED_HISTORY_DELETE;
    }

    /**
     * Whether `$expense` is still exactly what LegacyExpenseMigration left.
     *
     * @param \wpdb $wpdb
     * @param array<string, string> $t
     * @param array<string, mixed>|null $expense
     * @return array{reason: ?string, review_id: ?string, journal_entry_id: ?string, amount: ?int}
     */
    private static function inspect($wpdb, array $t, string $id, ?array $expense, bool $lock): array
    {
        $no = static fn (string $reason): array => ['reason' => $reason, 'review_id' => null, 'journal_entry_id' => null, 'amount' => null];

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

        $lines = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$t['lines']} WHERE expense_id = %s", $id), ARRAY_A) ?: [];
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

        return ['reason' => null, 'review_id' => (string) $migrated['id'], 'journal_entry_id' => $journalEntryId, 'amount' => $amount];
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

        error_log(LegacyExpenseMigration::LOG_PREFIX . " restore refused: {$reason}" . ($pairs === [] ? '' : ' (' . implode(', ', $pairs) . ')') . '; nothing was written');

        return array_merge($summary, ['outcome' => self::OUTCOME_REFUSED, 'reason' => $reason]);
    }

    /**
     * @param array{outcome: string, reason: ?string, expense_id: ?string, restored: string[], mismatches: array<string, string>} $summary
     * @return array{outcome: string, reason: ?string, expense_id: ?string, restored: string[], mismatches: array<string, string>}
     */
    private static function rolledBack(array $summary, ?string $id, string $reason): array
    {
        error_log(LegacyExpenseMigration::LOG_PREFIX . ' restore rolled back: ' . ($id === null ? $reason : "{$id}={$reason}") . '; nothing was restored');

        return array_merge($summary, ['outcome' => self::OUTCOME_ROLLED_BACK, 'reason' => $reason, 'expense_id' => $id]);
    }
}
