<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

/**
 * A FakeWpdb responder holding the handful of tables the schema 1.44.0
 * expense migration reads and writes, answering exactly the prepared
 * statements LegacyExpenseMigration and Installer issue. Also answers
 * Installer's "SHOW TABLES LIKE" from $existingTables.
 *
 * Transactions behave like InnoDB's for the migration's purposes: START
 * TRANSACTION snapshots every table, ROLLBACK restores the snapshot,
 * COMMIT keeps the changes. $failures makes one step fail the way
 * $wpdb reports it (query() returning false), to test the rollbacks.
 */
final class FakeExpenseMigrationDatabase
{
    /** @var array<string, array<int, array<string, mixed>>> table name (without prefix) => rows */
    public array $tables = [
        'stageart_expenses' => [],
        'stageart_expense_lines' => [],
        'stageart_expense_reviews' => [],
        'stageart_journal_entries' => [],
        'stageart_journal_entry_lines' => [],
        'stageart_accounts' => [],
        'stageart_productions' => [],
        'stageart_projects' => [],
    ];

    /** @var string[] tables (without prefix) Installer should see as already created */
    public array $existingTables = [];

    /** @var array<string, ?string> table name (without prefix) => storage engine information_schema reports */
    public array $engines = [
        'stageart_expenses' => 'InnoDB',
        'stageart_expense_reviews' => 'InnoDB',
    ];

    /**
     * Steps to fail: 'START' => true fails every START TRANSACTION;
     * 'UPDATE', 'INSERT' and 'COMMIT' => an Expense id fail that step for
     * that Expense only; 'RESTORE_UPDATE' and 'DELETE' => an Expense id
     * fail the restore's UPDATE / history DELETE for it; 'LOCK' => a
     * $locks entry ("journal_entry:<id>" etc.) fails that locking read
     * the way a deadlock does - no row, and $wpdb->last_error set.
     *
     * @var array<string, mixed>
     */
    public array $failures = [];

    /**
     * Every locking read (SELECT ... FOR UPDATE) in the order issued:
     * "expense:<id>", "journal_entry:<id>", "journal_entry_lines:<entry id>",
     * "expense_reviews:<expense id>".
     *
     * @var string[]
     */
    public array $locks = [];

    /** @var string[] every raw (unprepared or prepared) query string sent to query() */
    public array $queries = [];

    /**
     * Called with the Expense id and the tables (by reference) just
     * before SELECT ... FOR UPDATE answers - stands in for another
     * session committing a change between the preview and the lock.
     *
     * @var (callable(string, array): void)|null
     */
    public $onLock = null;

    /** @var array<string, array<int, array<string, mixed>>>|null the tables as they were at START TRANSACTION */
    private ?array $snapshot = null;

    /** The Expense the open transaction locked with SELECT ... FOR UPDATE. */
    private ?string $locked = null;

    /** @var string[] "START", "COMMIT", "ROLLBACK" in the order they were issued */
    public array $transactionLog = [];

    /**
     * @param array<int, mixed> $args
     * @return mixed
     */
    public function __invoke(string $method, array $args, FakeWpdb $wpdb)
    {
        $sql = (string) ($args[0] ?? '');
        $prepared = $wpdb->preparedFrom($sql);
        $template = $prepared['template'] ?? $sql;
        $values = $prepared['args'] ?? [];

        if ($method === 'get_var' && str_starts_with($template, 'SHOW TABLES LIKE')) {
            $table = (string) $values[0];

            return in_array(substr($table, strlen($wpdb->prefix)), $this->existingTables, true) ? $table : null;
        }

        if ($method === 'get_var' && str_starts_with($template, 'SELECT ENGINE FROM information_schema.TABLES')) {
            return $this->engines[substr((string) $values[0], strlen($wpdb->prefix))] ?? null;
        }

        if ($method === 'get_var' && preg_match('/^SELECT pj\.organization_id FROM \S+stageart_productions p JOIN \S+stageart_projects pj ON pj\.id = p\.project_id WHERE p\.id = %s$/', $template)) {
            $production = $this->where('stageart_productions', ['id' => $values[0]])[0] ?? null;
            $project = $production ? ($this->where('stageart_projects', ['id' => $production['project_id']])[0] ?? null) : null;

            return $project['organization_id'] ?? null;
        }

        if ($method === 'get_var' && preg_match('/^SELECT COUNT\(\*\) FROM \S+stageart_expense_reviews WHERE expense_id = %s$/', $template)) {
            return (string) count($this->where('stageart_expense_reviews', ['expense_id' => $values[0]]));
        }

        if ($method === 'get_col' && preg_match('/^SELECT id FROM \S+stageart_journal_entries WHERE source_event_type = %s AND source_event_id = %s ORDER BY id$/', $template)) {
            $ids = array_column($this->where('stageart_journal_entries', ['source_event_type' => $values[0], 'source_event_id' => $values[1]]), 'id');
            sort($ids);

            return $ids;
        }

        if ($method === 'get_row') {
            if (preg_match('/^SELECT \* FROM \S+stageart_expenses WHERE id = %s$/', $template)) {
                return $this->where('stageart_expenses', ['id' => $values[0]])[0] ?? null;
            }

            if (preg_match('/^SELECT \* FROM \S+stageart_journal_entries WHERE id = %s( FOR UPDATE)?$/', $template, $match)) {
                if (isset($match[1]) && ! $this->lock("journal_entry:{$values[0]}", $wpdb)) {
                    return null;
                }

                return $this->where('stageart_journal_entries', ['id' => $values[0]])[0] ?? null;
            }

            if (preg_match('/^SELECT id, source_event_type, source_event_id FROM \S+stageart_journal_entries WHERE id = %s$/', $template)) {
                $entry = $this->where('stageart_journal_entries', ['id' => $values[0]])[0] ?? null;

                return $entry === null ? null : ['id' => $entry['id'], 'source_event_type' => $entry['source_event_type'], 'source_event_id' => $entry['source_event_id']];
            }

            if (preg_match('/^SELECT \* FROM \S+stageart_expenses WHERE id = %s FOR UPDATE$/', $template)) {
                $this->locked = (string) $values[0];

                if (! $this->lock("expense:{$values[0]}", $wpdb)) {
                    return null;
                }

                if ($this->onLock !== null) {
                    // A change another session committed: in the live tables
                    // and in what a ROLLBACK here would restore.
                    ($this->onLock)($this->locked, $this->tables);

                    if ($this->snapshot !== null) {
                        ($this->onLock)($this->locked, $this->snapshot);
                    }
                }

                return $this->where('stageart_expenses', ['id' => $values[0]])[0] ?? null;
            }

            if (preg_match('/^SELECT id, organization_id, type FROM \S+stageart_accounts WHERE id = %s$/', $template)) {
                $account = $this->where('stageart_accounts', ['id' => $values[0]])[0] ?? null;

                return $account === null ? null : ['id' => $account['id'], 'organization_id' => $account['organization_id'], 'type' => $account['type']];
            }

            return null;
        }

        if ($method === 'get_results') {
            if (preg_match('/^SELECT \* FROM \S+stageart_expenses WHERE status = %s$/', $template)) {
                return $this->where('stageart_expenses', ['status' => $values[0]]);
            }

            if (preg_match('/^SELECT \* FROM \S+stageart_expense_lines WHERE expense_id = %s$/', $template)) {
                return $this->where('stageart_expense_lines', ['expense_id' => $values[0]]);
            }

            if (preg_match('/^SELECT \* FROM \S+stageart_journal_entries WHERE source_event_type = %s AND source_event_id = %s$/', $template)) {
                return $this->where('stageart_journal_entries', ['source_event_type' => $values[0], 'source_event_id' => $values[1]]);
            }

            if (preg_match('/^SELECT \* FROM \S+stageart_journal_entry_lines WHERE journal_entry_id = %s( FOR UPDATE)?$/', $template, $match)) {
                if (isset($match[1]) && ! $this->lock("journal_entry_lines:{$values[0]}", $wpdb)) {
                    return null;
                }

                return $this->where('stageart_journal_entry_lines', ['journal_entry_id' => $values[0]]);
            }

            if (preg_match('/^SELECT \* FROM \S+stageart_expense_reviews WHERE expense_id = %s( FOR UPDATE)?$/', $template, $match)) {
                if (isset($match[1]) && ! $this->lock("expense_reviews:{$values[0]}", $wpdb)) {
                    return null;
                }

                return $this->where('stageart_expense_reviews', ['expense_id' => $values[0]]);
            }

            return [];
        }

        if ($method === 'query') {
            $this->queries[] = $sql;

            if ($sql === 'START TRANSACTION') {
                $this->transactionLog[] = 'START';

                if (! empty($this->failures['START'])) {
                    return false;
                }

                $this->snapshot = $this->tables;

                return 0;
            }

            if ($sql === 'COMMIT') {
                $this->transactionLog[] = 'COMMIT';

                if (($this->failures['COMMIT'] ?? null) === $this->locked) {
                    return false;
                }

                $this->snapshot = null;
                $this->locked = null;

                return 0;
            }

            if ($sql === 'ROLLBACK') {
                $this->transactionLog[] = 'ROLLBACK';

                if ($this->snapshot !== null) {
                    $this->tables = $this->snapshot;
                }

                $this->snapshot = null;
                $this->locked = null;

                return 0;
            }

            if (str_starts_with($template, 'UPDATE ') && str_contains($template, 'stageart_expenses SET status = %s, revision = 0,')) {
                return $this->restoreExpense($values);
            }

            if (str_starts_with($template, 'DELETE FROM') && str_contains($template, 'stageart_expense_reviews')) {
                return $this->deleteReview($values);
            }

            if (str_starts_with($template, 'UPDATE ') && str_contains($template, 'stageart_expenses SET status = %s')) {
                return $this->migrateExpense($values);
            }

            if (str_starts_with($template, 'INSERT INTO') && str_contains($template, 'stageart_expense_reviews')) {
                return $this->insertReview($values);
            }

            return 1;
        }

        return FakeWpdbDefault::VALUE;
    }

    public function inTransaction(): bool
    {
        return $this->snapshot !== null;
    }

    /**
     * Records a locking read; false when $failures['LOCK'] fails it.
     */
    private function lock(string $what, FakeWpdb $wpdb): bool
    {
        $this->locks[] = $what;

        if (($this->failures['LOCK'] ?? null) === $what) {
            $wpdb->last_error = 'Deadlock found when trying to get lock; try restarting transaction';

            return false;
        }

        return true;
    }

    /**
     * Mirrors LegacyExpenseMigrationRestore's UPDATE: only a row still as
     * the migration left it is put back.
     *
     * @param array<int, mixed> $values
     * @return int|false
     */
    private function restoreExpense(array $values)
    {
        [$status, $id, $expected, $journalEntryId, $requested, $approved] = $values;

        if (($this->failures['RESTORE_UPDATE'] ?? null) === $id) {
            return false;
        }

        foreach ($this->tables['stageart_expenses'] as $index => $row) {
            if (
                $row['id'] === $id && $row['status'] === $expected && (int) $row['revision'] === 1
                && ($row['posted_journal_entry_id'] ?? null) === $journalEntryId
                && (int) $row['requested_amount'] === (int) $requested && (int) $row['approved_amount'] === (int) $approved
                && $row['submitted_by'] === $row['created_by'] && $row['submitted_at'] === $row['created_at']
                && $row['approved_by'] === $row['confirmed_by'] && $row['approved_at'] === $row['confirmed_at']
                && $row['rejected_by'] === null && $row['rejected_at'] === null && $row['rejection_reason'] === null
            ) {
                $this->tables['stageart_expenses'][$index] = array_merge($row, [
                    'status' => $status,
                    'revision' => 0,
                    'submitted_by' => null,
                    'submitted_at' => null,
                    'requested_amount' => null,
                    'approved_amount' => null,
                    'approved_by' => null,
                    'approved_at' => null,
                    'posted_journal_entry_id' => null,
                ]);

                return 1;
            }
        }

        return 0;
    }

    /**
     * @param array<int, mixed> $values
     * @return int|false
     */
    private function deleteReview(array $values)
    {
        [$id, $expenseId, $action] = $values;

        if (($this->failures['DELETE'] ?? null) === $expenseId) {
            return false;
        }

        foreach ($this->tables['stageart_expense_reviews'] as $index => $row) {
            if ($row['id'] === $id && $row['expense_id'] === $expenseId && $row['action'] === $action && (int) $row['revision'] === 1) {
                unset($this->tables['stageart_expense_reviews'][$index]);
                $this->tables['stageart_expense_reviews'] = array_values($this->tables['stageart_expense_reviews']);

                return 1;
            }
        }

        return 0;
    }

    /**
     * @param array<string, mixed> $conditions
     * @return array<int, array<string, mixed>>
     */
    private function where(string $table, array $conditions): array
    {
        return array_values(array_filter($this->tables[$table], static function (array $row) use ($conditions): bool {
            foreach ($conditions as $column => $value) {
                if ((string) ($row[$column] ?? '') !== (string) $value) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * Mirrors LegacyExpenseMigration's UPDATE ... WHERE id = %s AND
     * status = %s AND revision = 0 AND posted_journal_entry_id IS NULL.
     *
     * @param array<int, mixed> $values
     * @return int|false
     */
    private function migrateExpense(array $values)
    {
        [$status, $revision, $submittedBy, $submittedAt, $requested, $approved, $approvedBy, $approvedAt, $journalEntryId, $id, $expected] = $values;

        if (($this->failures['UPDATE'] ?? null) === $id) {
            return false;
        }

        foreach ($this->tables['stageart_expenses'] as $index => $row) {
            if ($row['id'] === $id && $row['status'] === $expected && (int) ($row['revision'] ?? 0) === 0 && ($row['posted_journal_entry_id'] ?? null) === null) {
                $this->tables['stageart_expenses'][$index] = array_merge($row, [
                    'status' => $status,
                    'revision' => $revision,
                    'submitted_by' => $submittedBy,
                    'submitted_at' => $submittedAt,
                    'requested_amount' => $requested,
                    'approved_amount' => $approved,
                    'approved_by' => $approvedBy,
                    'approved_at' => $approvedAt,
                    'posted_journal_entry_id' => $journalEntryId,
                ]);

                return 1;
            }
        }

        return 0;
    }

    /**
     * Plain INSERT against UNIQUE (expense_id, revision, action): a
     * duplicate is an error, as in MySQL.
     *
     * @param array<int, mixed> $values
     * @return int|false
     */
    private function insertReview(array $values)
    {
        [$id, $expenseId, $revision, $action, $requested, $approved, $snapshot, $actor, $actedAt] = $values;

        if (($this->failures['INSERT'] ?? null) === $expenseId) {
            return false;
        }

        foreach ($this->tables['stageart_expense_reviews'] as $row) {
            if ($row['expense_id'] === $expenseId && (int) $row['revision'] === (int) $revision && $row['action'] === $action) {
                return false;
            }
        }

        $this->tables['stageart_expense_reviews'][] = [
            'id' => $id,
            'expense_id' => $expenseId,
            'revision' => $revision,
            'action' => $action,
            'requested_amount' => $requested,
            'approved_amount' => $approved,
            'lines_snapshot' => $snapshot,
            'actor_person_id' => $actor,
            'acted_at' => $actedAt,
        ];

        return 1;
    }
}
