<?php

declare(strict_types=1);

namespace StageArt\Accounting;

use StageArt\Domain\Shared\Uuid;
use Throwable;

/**
 * Schema 1.44.0 (修正8): carries pre-workflow CONFIRMED Expenses over to
 * the approval workflow, following LegacyExpenseMigrationClassifier's
 * decision for each one.
 *
 * Deliberately not part of the schema upgrade: deploying the code and
 * upgrading the schema never changes an Expense. An operator runs
 * plugin/scripts/legacy_expense_migration.php through WP-CLI
 * (docs/handoffs/LegacyExpenseMigration-Runbook.md):
 *
 * - preview() only reads. It lists the candidates and the rows left for
 *   review with their reasons, reports whether the written tables are
 *   transactional, and returns a fingerprint of that plan.
 * - execute() writes only when given the fingerprint of the plan it
 *   finds itself, i.e. the operator confirmed exactly what will change.
 *   Each Expense is migrated in its own transaction: the row is locked
 *   and classified again, its status updated and its MIGRATED history
 *   added, and any failure - including the COMMIT - rolls that Expense
 *   back to CONFIRMED and is logged as "<expense id>=<reason code>".
 *
 * Only CONFIRMED rows are read, every UPDATE is conditional on the row
 * still being CONFIRMED with untouched workflow columns, and no Journal
 * Entry is ever written, so running it again changes nothing. A migrated
 * row's previous values are known exactly: status CONFIRMED and the
 * workflow columns at their defaults (the classifier migrates nothing
 * else), which is what the runbook's restore step puts back.
 */
final class LegacyExpenseMigration
{
    public const LOG_PREFIX = '[StageArt legacy expense migration]';

    public const OUTCOME_COMPLETED = 'COMPLETED';
    public const OUTCOME_REFUSED = 'REFUSED';

    public const REFUSED_NOT_TRANSACTIONAL = 'NOT_TRANSACTIONAL';
    public const REFUSED_PLAN_CHANGED = 'PLAN_CHANGED';

    public const FAILED_TRANSACTION_START = 'TRANSACTION_START_FAILED';
    public const FAILED_CHANGED_SINCE_PREVIEW = 'CHANGED_SINCE_PREVIEW';
    public const FAILED_UPDATE = 'UPDATE_FAILED';
    public const FAILED_HISTORY_INSERT = 'HISTORY_INSERT_FAILED';
    public const FAILED_COMMIT = 'COMMIT_FAILED';
    public const FAILED_UNEXPECTED = 'UNEXPECTED_ERROR';

    /** The tables execute() writes; both must support transactions. */
    private const WRITTEN_TABLES = ['stageart_expenses', 'stageart_expense_reviews'];

    /**
     * Reads only: nothing is written, locked or logged.
     *
     * @param \wpdb $wpdb
     * @return array{
     *     confirmed: int,
     *     candidates: array<string, array{journal_entry_id: string, amount: int}>,
     *     manual_review: array<string, string>,
     *     storage_engines: array<string, ?string>,
     *     transactional: bool,
     *     fingerprint: string
     * }
     */
    public static function preview($wpdb): array
    {
        $t = self::tables($wpdb);
        $candidates = [];
        $manual = [];

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$t['expenses']} WHERE status = %s", 'CONFIRMED'),
            ARRAY_A
        ) ?: [];

        foreach ($rows as $expense) {
            $id = (string) $expense['id'];
            $decision = self::classifyRow($wpdb, $t, $expense);

            if ($decision['decision'] === LegacyExpenseMigrationClassifier::MIGRATE_TO_POSTED) {
                $candidates[$id] = ['journal_entry_id' => (string) $decision['journal_entry_id'], 'amount' => (int) $decision['amount']];
            } elseif ($decision['decision'] === LegacyExpenseMigrationClassifier::MANUAL_REVIEW) {
                $manual[$id] = (string) $decision['reason'];
            }
        }

        ksort($candidates);
        ksort($manual);

        $engines = [];

        foreach (self::WRITTEN_TABLES as $table) {
            $engine = $wpdb->get_var($wpdb->prepare(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                $wpdb->prefix . $table
            ));
            $engines[$wpdb->prefix . $table] = $engine === null ? null : (string) $engine;
        }

        return [
            'confirmed' => count($rows),
            'candidates' => $candidates,
            'manual_review' => $manual,
            'storage_engines' => $engines,
            'transactional' => array_filter($engines, static fn (?string $e): bool => $e === null || strcasecmp($e, 'InnoDB') !== 0) === [],
            'fingerprint' => substr(hash('sha256', (string) json_encode([$candidates, $manual])), 0, 16),
        ];
    }

    /**
     * Migrates the previewed candidates, but only when `$confirmedFingerprint`
     * is the fingerprint of the plan as it stands now and the written
     * tables are transactional; otherwise nothing is written.
     *
     * @param \wpdb $wpdb
     * @return array{
     *     outcome: string,
     *     refused_reason: ?string,
     *     migrated: string[],
     *     failed: array<string, string>,
     *     manual_review: array<string, string>
     * }
     */
    public static function execute($wpdb, string $confirmedFingerprint): array
    {
        $plan = self::preview($wpdb);
        $summary = ['outcome' => self::OUTCOME_COMPLETED, 'refused_reason' => null, 'migrated' => [], 'failed' => [], 'manual_review' => $plan['manual_review']];

        if (! $plan['transactional']) {
            return self::refuse($summary, self::REFUSED_NOT_TRANSACTIONAL);
        }

        if (! hash_equals($plan['fingerprint'], $confirmedFingerprint)) {
            return self::refuse($summary, self::REFUSED_PLAN_CHANGED);
        }

        $t = self::tables($wpdb);

        foreach ($plan['candidates'] as $id => $expected) {
            $failure = self::migrateOne($wpdb, $t, (string) $id, $expected);

            if ($failure === null) {
                $summary['migrated'][] = (string) $id;
            } else {
                $summary['failed'][(string) $id] = $failure;
                error_log(self::LOG_PREFIX . " failed: {$id}={$failure}");
            }
        }

        if ($summary['manual_review'] !== []) {
            $pairs = [];

            foreach ($summary['manual_review'] as $id => $reason) {
                $pairs[] = "{$id}={$reason}";
            }

            error_log(self::LOG_PREFIX . ' left CONFIRMED for manual review: ' . implode(', ', $pairs));
        }

        error_log(sprintf(
            '%s completed: migrated=%d failed=%d manual_review=%d',
            self::LOG_PREFIX,
            count($summary['migrated']),
            count($summary['failed']),
            count($summary['manual_review'])
        ));

        return $summary;
    }

    /**
     * One Expense, one transaction. Returns null once committed, or the
     * reason it was rolled back.
     *
     * @param \wpdb $wpdb
     * @param array<string, string> $t
     * @param array{journal_entry_id: string, amount: int} $expected
     */
    private static function migrateOne($wpdb, array $t, string $id, array $expected): ?string
    {
        if ($wpdb->query('START TRANSACTION') === false) {
            return self::FAILED_TRANSACTION_START;
        }

        try {
            $failure = self::writeOne($wpdb, $t, $id, $expected);
        } catch (Throwable $exception) {
            $failure = self::FAILED_UNEXPECTED;
        }

        if ($failure === null && $wpdb->query('COMMIT') === false) {
            $failure = self::FAILED_COMMIT;
        }

        if ($failure !== null) {
            $wpdb->query('ROLLBACK');
        }

        return $failure;
    }

    /**
     * @param \wpdb $wpdb
     * @param array<string, string> $t
     * @param array{journal_entry_id: string, amount: int} $expected
     */
    private static function writeOne($wpdb, array $t, string $id, array $expected): ?string
    {
        $expense = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$t['expenses']} WHERE id = %s FOR UPDATE", $id),
            ARRAY_A
        );

        if (! is_array($expense)) {
            return self::FAILED_CHANGED_SINCE_PREVIEW;
        }

        $lines = self::linesOf($wpdb, $t, $id);
        $decision = self::classifyRow($wpdb, $t, $expense, $lines);

        if (
            $decision['decision'] !== LegacyExpenseMigrationClassifier::MIGRATE_TO_POSTED
            || $decision['journal_entry_id'] !== $expected['journal_entry_id']
            || $decision['amount'] !== $expected['amount']
        ) {
            return self::FAILED_CHANGED_SINCE_PREVIEW;
        }

        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE {$t['expenses']} SET status = %s, revision = %d, submitted_by = %s, submitted_at = %s,
                requested_amount = %d, approved_amount = %d, approved_by = %s, approved_at = %s,
                posted_journal_entry_id = %s
            WHERE id = %s AND status = %s AND revision = 0 AND posted_journal_entry_id IS NULL",
            'POSTED',
            1,
            $expense['created_by'],
            $expense['created_at'],
            $expected['amount'],
            $expected['amount'],
            $expense['confirmed_by'],
            $expense['confirmed_at'],
            $expected['journal_entry_id'],
            $id,
            'CONFIRMED'
        ));

        if ($updated !== 1) {
            return self::FAILED_UPDATE;
        }

        $snapshot = array_map(
            static fn (array $line): array => [
                'account_id' => (string) $line['account_id'],
                'amount' => (int) $line['amount'],
                'description' => $line['description'] ?? null,
            ],
            $lines
        );

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT INTO {$t['reviews']}
                (id, expense_id, revision, action, requested_amount, approved_amount, lines_snapshot, reason, actor_person_id, acted_at)
            VALUES (%s, %s, %d, %s, %d, %d, %s, NULL, %s, %s)",
            Uuid::generate(),
            $id,
            1,
            'MIGRATED',
            $expected['amount'],
            $expected['amount'],
            (string) json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            $expense['confirmed_by'],
            $expense['confirmed_at']
        ));

        return $inserted === 1 ? null : self::FAILED_HISTORY_INSERT;
    }

    /**
     * Gathers everything the classifier needs for one Expense row.
     *
     * @param \wpdb $wpdb
     * @param array<string, string> $t
     * @param array<string, mixed> $expense
     * @param array<int, array<string, mixed>>|null $lines
     * @return array{decision: string, reason: ?string, journal_entry_id: ?string, amount: ?int}
     */
    private static function classifyRow($wpdb, array $t, array $expense, ?array $lines = null): array
    {
        $id = (string) $expense['id'];
        $lines ??= self::linesOf($wpdb, $t, $id);

        $entries = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$t['journal_entries']} WHERE source_event_type = %s AND source_event_id = %s",
                'ExpenseConfirmed',
                $id
            ),
            ARRAY_A
        ) ?: [];

        $accounts = [];

        foreach ($entries as $index => $entry) {
            $entryLines = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$t['journal_entry_lines']} WHERE journal_entry_id = %s", $entry['id']),
                ARRAY_A
            ) ?: [];
            $entries[$index]['lines'] = $entryLines;

            foreach ($entryLines as $entryLine) {
                $accountId = (string) ($entryLine['account_id'] ?? '');

                if ($accountId !== '' && ! array_key_exists($accountId, $accounts)) {
                    $account = $wpdb->get_row(
                        $wpdb->prepare("SELECT id, organization_id, type FROM {$t['accounts']} WHERE id = %s", $accountId),
                        ARRAY_A
                    );

                    if (is_array($account)) {
                        $accounts[$accountId] = $account;
                    }
                }
            }
        }

        $organizationId = $wpdb->get_var($wpdb->prepare(
            "SELECT pj.organization_id FROM {$t['productions']} p JOIN {$t['projects']} pj ON pj.id = p.project_id WHERE p.id = %s",
            (string) ($expense['production_id'] ?? '')
        ));

        $historyCount = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t['reviews']} WHERE expense_id = %s", $id));

        return LegacyExpenseMigrationClassifier::classify($expense, $lines, $entries, [
            'organization_id' => $organizationId === null ? null : (string) $organizationId,
            'accounts' => $accounts,
            'history_count' => (int) $historyCount,
        ]);
    }

    /**
     * @param \wpdb $wpdb
     * @param array<string, string> $t
     * @return array<int, array<string, mixed>>
     */
    private static function linesOf($wpdb, array $t, string $id): array
    {
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$t['lines']} WHERE expense_id = %s", $id),
            ARRAY_A
        ) ?: [];
    }

    /**
     * @param array{outcome: string, refused_reason: ?string, migrated: string[], failed: array<string, string>, manual_review: array<string, string>} $summary
     * @return array{outcome: string, refused_reason: ?string, migrated: string[], failed: array<string, string>, manual_review: array<string, string>}
     */
    private static function refuse(array $summary, string $reason): array
    {
        error_log(self::LOG_PREFIX . " refused: {$reason}; nothing was written");

        return array_merge($summary, ['outcome' => self::OUTCOME_REFUSED, 'refused_reason' => $reason]);
    }

    /**
     * @param \wpdb $wpdb
     * @return array<string, string>
     */
    private static function tables($wpdb): array
    {
        $p = $wpdb->prefix;

        return [
            'expenses' => "{$p}stageart_expenses",
            'lines' => "{$p}stageart_expense_lines",
            'reviews' => "{$p}stageart_expense_reviews",
            'journal_entries' => "{$p}stageart_journal_entries",
            'journal_entry_lines' => "{$p}stageart_journal_entry_lines",
            'accounts' => "{$p}stageart_accounts",
            'productions' => "{$p}stageart_productions",
            'projects' => "{$p}stageart_projects",
        ];
    }
}
