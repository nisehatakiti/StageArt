<?php

declare(strict_types=1);

namespace StageArt\Tests\Accounting;

use PHPUnit\Framework\TestCase;
use StageArt\Accounting\LegacyExpenseMigration;
use StageArt\Accounting\LegacyExpenseMigrationRestore as R;
use StageArt\Tests\Support\FakeExpenseMigrationDatabase;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\LegacyExpenseFixture;

/**
 * The runbook's partial restore against a fake database: only Expenses
 * still exactly as the migration left them go back to CONFIRMED, all
 * listed ones in one transaction or none, and no Journal Entry changes.
 * The history stays append-only: the MIGRATED row is kept and a
 * MIGRATION_REVERTED row is added in the same transaction
 * (ExpenseApprovalWorkflow.md Version 1.8).
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LegacyExpenseMigrationRestoreTest extends TestCase
{
    /** The operator's StageArt Person, as the WP-CLI script resolves it. */
    private const ACTOR = '44444444-4444-4444-8444-444444444444';

    private const REASON = '移行対象の判断誤りのため（承認番号 R-1）';
    private FakeExpenseMigrationDatabase $db;
    /** @var FakeWpdb untyped: a failure report is unserialized where the wpdb stub class is not loaded */
    private $wpdb;
    private LegacyExpenseFixture $data;

    /** @var \StageArt\Accounting\LegacyExpenseMigrationOperator a qualifying operator (untyped, like $wpdb) */
    private $operator;
    private string $log;

    /** @var array<string, array<int, array<string, mixed>>> the tables before the migration */
    private array $beforeMigration;

    /** @var array<string, array<int, array<string, mixed>>> the tables right after it */
    private array $afterMigration;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../Support/wordpress-stubs.php';
        $this->log = (string) tempnam(sys_get_temp_dir(), 'stageart-restore-log');
        ini_set('error_log', $this->log);

        $this->db = new FakeExpenseMigrationDatabase();
        $this->wpdb = new \wpdb($this->db);
        $this->data = new LegacyExpenseFixture($this->db);
        $this->operator = $this->data->operator($this->wpdb);

        $this->data->expense('migratable', 'CONFIRMED', [3000, 2000], ['payer_person_id' => 'payer']);
        $this->data->entry('je-ok', 'migratable', 'POSTED', [3000, 2000]);
        $this->data->expense('migratable-2', 'CONFIRMED', [800]);
        $this->data->entry('je-ok-2', 'migratable-2', 'POSTED', [800]);
        $this->data->expense('draft-entry', 'CONFIRMED', [1000]);
        $this->data->entry('je-draft', 'draft-entry', 'DRAFT', [1000]);

        $this->beforeMigration = $this->db->businessTables();
        $result = LegacyExpenseMigration::execute($this->wpdb, LegacyExpenseMigration::preview($this->wpdb)['fingerprint'], $this->operator);
        $this->assertSame(['migratable', 'migratable-2'], $result['migrated']);
        $this->afterMigration = $this->db->businessTables();

        $this->db->transactionLog = [];
        $this->db->locks = [];
        $this->wpdb->calls = [];
        file_put_contents($this->log, '');
    }

    protected function tearDown(): void
    {
        @unlink($this->log);
    }

    private function logged(): string
    {
        return (string) file_get_contents($this->log);
    }

    /**
     * @param string[] $ids
     * @return array{outcome: string, reason: ?string, expense_id: ?string, restored: string[], mismatches: array<string, string>}
     */
    private function restore(array $ids): array
    {
        return R::restore($this->wpdb, $ids, $this->operator, self::REASON);
    }

    /**
     * @return string[] the actions of the Expense's history rows, in the order stored
     */
    private function actionsOf(string $expenseId): array
    {
        return array_column($this->data->historyOf($expenseId), 'action');
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function changeExpense(string $id, array $changes): void
    {
        foreach ($this->db->tables['stageart_expenses'] as $index => $row) {
            if ($row['id'] === $id) {
                $this->db->tables['stageart_expenses'][$index] = array_merge($row, $changes);
            }
        }
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function journalTables(): array
    {
        return [
            'entries' => $this->db->tables['stageart_journal_entries'],
            'lines' => $this->db->tables['stageart_journal_entry_lines'],
        ];
    }

    public function test_the_check_reports_each_expense_and_writes_nothing(): void
    {
        $result = R::check($this->wpdb, ['migratable', 'draft-entry', 'missing', 'migratable']);

        $this->assertSame(['migratable' => null, 'draft-entry' => R::STATE_CHANGED, 'missing' => R::NOT_FOUND], $result);
        $this->assertSame($this->afterMigration, $this->db->businessTables());
        $this->assertSame([], $this->wpdb->callsTo('query'));
        $this->assertSame([], $this->db->locks);
    }

    public function test_a_restore_puts_back_only_the_listed_expense_exactly_as_it_was(): void
    {
        $journalBefore = $this->journalTables();
        $totalsBefore = $this->data->journalTotals();

        $result = $this->restore(['migratable']);

        $this->assertSame(R::OUTCOME_RESTORED, $result['outcome']);
        $this->assertSame(['migratable'], $result['restored']);
        $this->assertSame(['START', 'COMMIT'], $this->db->transactionLog);

        $restored = array_column($this->db->tables['stageart_expenses'], null, 'id')['migratable'];
        $original = array_column($this->beforeMigration['stageart_expenses'], null, 'id')['migratable'];
        $this->assertEquals($original, $restored);

        $this->assertSame('POSTED', $this->data->expensesById()['migratable-2']['status']);
        $this->assertSame(['MIGRATED'], $this->actionsOf('migratable-2'));
        $this->assertSame($journalBefore, $this->journalTables());
        $this->assertSame($totalsBefore, $this->data->journalTotals());
        $this->assertStringContainsString('restored to CONFIRMED: migratable; recorded MIGRATION_REVERTED at ', $this->logged());
    }

    public function test_a_restore_of_several_expenses_restores_them_in_one_transaction(): void
    {
        $result = $this->restore(['migratable', 'migratable-2']);

        $this->assertSame(['migratable', 'migratable-2'], $result['restored']);
        $this->assertSame(['START', 'COMMIT'], $this->db->transactionLog);
        $this->assertEquals($this->beforeMigration['stageart_expenses'], $this->db->tables['stageart_expenses']);
        $this->assertSame(['MIGRATED', 'MIGRATION_REVERTED'], $this->actionsOf('migratable'));
        $this->assertSame(['MIGRATED', 'MIGRATION_REVERTED'], $this->actionsOf('migratable-2'));
        $this->assertSame([
            'expense:migratable', 'expense_reviews:migratable', 'expense_lines:migratable',
            'expense:migratable-2', 'expense_reviews:migratable-2', 'expense_lines:migratable-2',
        ], $this->db->locks);
    }

    /**
     * @dataProvider changesSinceTheMigration
     */
    public function test_an_expense_changed_since_the_migration_is_not_restored(string $change, string $reason): void
    {
        $this->change($change);
        $before = $this->db->businessTables();

        $result = $this->restore(['migratable-2', 'migratable']);

        $this->assertSame(R::OUTCOME_REFUSED, $result['outcome']);
        $this->assertSame(R::REFUSED_TARGET_MISMATCH, $result['reason']);
        $this->assertSame(['migratable' => $reason], $result['mismatches']);
        $this->assertSame([], $result['restored']);
        $this->assertSame($before, $this->db->businessTables());
        $this->assertSame([], $this->db->transactionLog);
        $this->assertStringContainsString("restore refused: TARGET_MISMATCH (migratable={$reason}); no Expense was changed", $this->logged());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function changesSinceTheMigration(): array
    {
        return [
            'no longer POSTED' => ['status', R::STATE_CHANGED],
            'a later revision' => ['revision', R::STATE_CHANGED],
            'rejected' => ['rejected', R::STATE_CHANGED],
            'another approved amount' => ['approved amount', R::MODIFIED_SINCE_MIGRATION],
            'another approver' => ['approver', R::MODIFIED_SINCE_MIGRATION],
            'another submitter' => ['submitter', R::MODIFIED_SINCE_MIGRATION],
            'linked to another journal entry' => ['other journal entry', R::JOURNAL_ENTRY_MISMATCH],
            'linked to a missing journal entry' => ['missing journal entry', R::JOURNAL_ENTRY_MISMATCH],
            'a further history row' => ['further history', R::HISTORY_MISMATCH],
            'the history row is not the migration' => ['history action', R::HISTORY_MISMATCH],
            'no history row' => ['no history', R::HISTORY_MISMATCH],
            'edited lines' => ['edited lines', R::LINES_CHANGED],
            'a line added' => ['added line', R::LINES_CHANGED],
        ];
    }

    /**
     * What another step may have done to Expense "migratable" after the migration.
     */
    private function change(string $change): void
    {
        $tables = &$this->db->tables;

        switch ($change) {
            case 'status':
                $this->changeExpense('migratable', ['status' => 'SUBMITTED']);
                break;
            case 'revision':
                $this->changeExpense('migratable', ['revision' => '2']);
                break;
            case 'rejected':
                $this->changeExpense('migratable', ['rejected_by' => 'manager']);
                break;
            case 'approved amount':
                $this->changeExpense('migratable', ['approved_amount' => '4000']);
                break;
            case 'approver':
                $this->changeExpense('migratable', ['approved_by' => 'someone-else']);
                break;
            case 'submitter':
                $this->changeExpense('migratable', ['submitted_by' => 'someone-else']);
                break;
            case 'other journal entry':
                $this->data->entry('je-approved', 'migratable', 'POSTED', [5000], null, ['source_event_type' => 'ExpenseApproved']);
                $this->changeExpense('migratable', ['posted_journal_entry_id' => 'je-approved']);
                break;
            case 'missing journal entry':
                $this->changeExpense('migratable', ['posted_journal_entry_id' => 'je-gone']);
                break;
            case 'further history':
                $tables['stageart_expense_reviews'][] = [
                    'id' => 'later', 'expense_id' => 'migratable', 'revision' => 2, 'action' => 'SUBMITTED',
                    'requested_amount' => 5000, 'approved_amount' => null, 'lines_snapshot' => '[]', 'actor_person_id' => 'creator', 'acted_at' => '2026-11-01 10:00:00',
                ];
                break;
            case 'history action':
                foreach ($tables['stageart_expense_reviews'] as $index => $row) {
                    if ($row['expense_id'] === 'migratable') {
                        $tables['stageart_expense_reviews'][$index]['action'] = 'APPROVED';
                    }
                }
                break;
            case 'no history':
                $tables['stageart_expense_reviews'] = array_values(array_filter(
                    $tables['stageart_expense_reviews'],
                    static fn (array $row): bool => $row['expense_id'] !== 'migratable'
                ));
                break;
            case 'edited lines':
                foreach ($tables['stageart_expense_lines'] as $index => $row) {
                    if ($row['expense_id'] === 'migratable') {
                        $tables['stageart_expense_lines'][$index]['description'] = '衣装';
                    }
                }
                break;
            case 'added line':
                $tables['stageart_expense_lines'][] = [
                    'id' => 'extra', 'expense_id' => 'migratable', 'account_id' => 'acc-expense', 'amount' => '100', 'description' => '小道具',
                ];
                break;
            default:
                $this->fail("Unknown change {$change}.");
        }
    }

    public function test_an_unknown_id_refuses_the_whole_list(): void
    {
        $result = $this->restore(['migratable', 'missing']);

        $this->assertSame(R::OUTCOME_REFUSED, $result['outcome']);
        $this->assertSame(['missing' => R::NOT_FOUND], $result['mismatches']);
        $this->assertSame($this->afterMigration, $this->db->businessTables());
    }

    /**
     * Changed by another session after the check, before its lock: the
     * check under the lock catches it and the whole restore rolls back,
     * including the Expense already restored before it.
     */
    public function test_an_expense_changed_before_its_lock_rolls_the_whole_restore_back(): void
    {
        $this->db->onLock = static function (string $id, array &$tables): void {
            if ($id !== 'migratable-2') {
                return;
            }

            foreach ($tables['stageart_expenses'] as $index => $row) {
                if ($row['id'] === 'migratable-2') {
                    $tables['stageart_expenses'][$index]['rejected_by'] = 'manager';
                }
            }
        };

        $result = $this->restore(['migratable', 'migratable-2']);

        $this->assertSame(R::OUTCOME_ROLLED_BACK, $result['outcome']);
        $this->assertSame(R::STATE_CHANGED, $result['reason']);
        $this->assertSame('migratable-2', $result['expense_id']);
        $this->assertSame(['START', 'ROLLBACK'], $this->db->transactionLog);
        $this->assertSame('POSTED', $this->data->expensesById()['migratable']['status']);
        $this->assertCount(1, $this->data->historyOf('migratable'));
        $this->assertStringContainsString('restore rolled back: migratable-2=STATE_CHANGED; nothing was restored', $this->logged());
    }

    /**
     * History or lines another session changed after the check, while
     * the restore was already holding an earlier Expense: the locked
     * re-check sees the current rows and rolls everything back.
     *
     * @dataProvider changesAfterTheCheck
     */
    public function test_history_or_lines_changed_after_the_check_roll_the_whole_restore_back(string $change, string $reason): void
    {
        $this->db->onLock = static function (string $id, array &$tables) use ($change): void {
            if ($id !== 'migratable-2') {
                return;
            }

            if ($change === 'history') {
                $tables['stageart_expense_reviews'][] = [
                    'id' => 'later', 'expense_id' => 'migratable-2', 'revision' => 2, 'action' => 'SUBMITTED',
                    'requested_amount' => 800, 'approved_amount' => null, 'lines_snapshot' => '[]', 'actor_person_id' => 'creator', 'acted_at' => '2026-11-01 10:00:00',
                ];

                return;
            }

            foreach ($tables['stageart_expense_lines'] as $index => $row) {
                if ($row['expense_id'] === 'migratable-2') {
                    $tables['stageart_expense_lines'][$index]['amount'] = '900';
                }
            }
        };

        $result = $this->restore(['migratable', 'migratable-2']);

        $this->assertSame(R::OUTCOME_ROLLED_BACK, $result['outcome']);
        $this->assertSame($reason, $result['reason']);
        $this->assertSame('migratable-2', $result['expense_id']);
        $this->assertSame(['START', 'ROLLBACK'], $this->db->transactionLog);
        $this->assertSame('POSTED', $this->data->expensesById()['migratable']['status']);
        $this->assertCount(1, $this->data->historyOf('migratable'));
        $this->assertSame('POSTED', $this->data->expensesById()['migratable-2']['status']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function changesAfterTheCheck(): array
    {
        return [
            'a history row added' => ['history', R::HISTORY_MISMATCH],
            'a line amount changed' => ['lines', R::LINES_CHANGED],
        ];
    }

    /**
     * Whatever order the operator lists them in, Expenses are locked in
     * id order, so two overlapping restores cannot lock in opposite orders.
     */
    public function test_expenses_are_locked_in_id_order(): void
    {
        $result = $this->restore(['migratable-2', 'migratable']);

        $this->assertSame(R::OUTCOME_RESTORED, $result['outcome']);
        $this->assertSame(['migratable-2', 'migratable'], $result['restored']);
        $expenseLocks = array_values(array_filter($this->db->locks, static fn (string $lock): bool => str_starts_with($lock, 'expense:')));
        $this->assertSame(['expense:migratable', 'expense:migratable-2'], $expenseLocks);
    }

    /**
     * The MIGRATED row is kept untouched and the MIGRATION_REVERTED row
     * beside it records what was undone, why, by whom and when - the
     * history alone tells the story, in time order.
     */
    public function test_the_migration_row_is_kept_and_the_revert_is_recorded_beside_it(): void
    {
        $migrated = $this->data->historyOf('migratable')[0];

        $this->restore(['migratable']);

        [$kept, $reverted] = $this->data->historyOf('migratable');
        $this->assertSame($migrated, $kept);
        $this->assertSame('MIGRATION_REVERTED', $reverted['action']);
        $this->assertSame(1, (int) $reverted['revision']);
        $this->assertSame(5000, (int) $reverted['requested_amount']);
        $this->assertSame(5000, (int) $reverted['approved_amount']);
        $this->assertSame(json_decode((string) $migrated['lines_snapshot'], true), json_decode((string) $reverted['lines_snapshot'], true));
        $this->assertSame(self::REASON, $reverted['reason']);
        $this->assertSame(self::ACTOR, $reverted['actor_person_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $reverted['acted_at']);
        $this->assertGreaterThan((string) $kept['acted_at'], (string) $reverted['acted_at']);
        $this->assertNotSame($kept['id'], $reverted['id']);
    }

    /**
     * Required test 3: when the MIGRATION_REVERTED row cannot be added,
     * the Expense update of the same transaction is rolled back too.
     */
    public function test_a_failed_revert_record_rolls_the_expense_update_back(): void
    {
        $this->db->failures['INSERT'] = 'migratable';

        $result = $this->restore(['migratable']);

        $this->assertSame(R::OUTCOME_ROLLED_BACK, $result['outcome']);
        $this->assertSame(R::FAILED_HISTORY_INSERT, $result['reason']);
        $this->assertSame('migratable', $result['expense_id']);
        $this->assertSame(['START', 'ROLLBACK'], $this->db->transactionLog);
        $this->assertSame('POSTED', $this->data->expensesById()['migratable']['status']);
        $this->assertSame(['MIGRATED'], $this->actionsOf('migratable'));
        $this->assertSame($this->afterMigration, $this->db->businessTables());
        $this->assertStringContainsString('restore rolled back: migratable=HISTORY_INSERT_FAILED; nothing was restored', $this->logged());
    }

    /**
     * Required test 5: a reverted Expense is not a migration candidate
     * again; it is reported for review as MIGRATION_REVERTED, and running
     * the migration with the new plan leaves it CONFIRMED.
     */
    public function test_a_reverted_expense_is_never_migrated_again(): void
    {
        $this->restore(['migratable']);

        $plan = LegacyExpenseMigration::preview($this->wpdb);
        $result = LegacyExpenseMigration::execute($this->wpdb, $plan['fingerprint'], $this->operator);

        $this->assertSame([], $plan['candidates']);
        $this->assertSame('MIGRATION_REVERTED', $plan['manual_review']['migratable']);
        $this->assertSame([], $result['migrated']);
        $this->assertSame('CONFIRMED', $this->data->expensesById()['migratable']['status']);
        $this->assertSame(['MIGRATED', 'MIGRATION_REVERTED'], $this->actionsOf('migratable'));
    }

    /**
     * Required test 6: migration history that does not add up is left for
     * review, whatever the row looks like otherwise.
     *
     * @dataProvider inconsistentHistories
     *
     * @param string[] $extraActions rows added to the reverted Expense's history, as "ACTION@revision"
     */
    public function test_inconsistent_migration_history_is_left_for_review(array $extraActions, bool $dropRevert): void
    {
        $this->restore(['migratable']);

        foreach ($extraActions as $index => $extra) {
            [$action, $revision] = explode('@', $extra);
            $this->db->tables['stageart_expense_reviews'][] = [
                'id' => "extra-{$index}", 'expense_id' => 'migratable', 'revision' => (int) $revision, 'action' => $action,
                'requested_amount' => 5000, 'approved_amount' => 5000, 'lines_snapshot' => '[]', 'reason' => null,
                'actor_person_id' => self::ACTOR, 'acted_at' => '2026-11-02 10:00:00',
            ];
        }

        if ($dropRevert) {
            $this->db->tables['stageart_expense_reviews'] = array_values(array_filter(
                $this->db->tables['stageart_expense_reviews'],
                static fn (array $row): bool => ! ($row['expense_id'] === 'migratable' && $row['action'] === 'MIGRATION_REVERTED')
            ));
        }

        $plan = LegacyExpenseMigration::preview($this->wpdb);
        LegacyExpenseMigration::execute($this->wpdb, $plan['fingerprint'], $this->operator);

        $this->assertArrayNotHasKey('migratable', $plan['candidates']);
        $this->assertSame('HISTORY_INCONSISTENT', $plan['manual_review']['migratable']);
        $this->assertSame('CONFIRMED', $this->data->expensesById()['migratable']['status']);
    }

    /**
     * @return array<string, array{string[], bool}>
     */
    public static function inconsistentHistories(): array
    {
        return [
            'migrated, not reverted, still CONFIRMED' => [[], true],
            'a second migration' => [['MIGRATED@2'], false],
            'a second revert' => [['MIGRATION_REVERTED@2'], false],
            'another step after the revert' => [['SUBMITTED@1'], false],
        ];
    }

    /**
     * A reverted Expense is CONFIRMED again, not as the migration left it,
     * so a second restore of it is refused before anything is written.
     */
    public function test_a_reverted_expense_cannot_be_restored_again(): void
    {
        $this->restore(['migratable']);
        $this->db->transactionLog = [];
        $before = $this->db->businessTables();

        $result = $this->restore(['migratable']);

        $this->assertSame(R::OUTCOME_REFUSED, $result['outcome']);
        $this->assertSame(['migratable' => R::STATE_CHANGED], $result['mismatches']);
        $this->assertSame($before, $this->db->businessTables());
        $this->assertSame([], $this->db->transactionLog);
    }

    /**
     * Without a reason, or with an over-long one, nothing is read for
     * writing and nothing is written. (A restore cannot be called without
     * an operator at all: see LegacyExpenseMigrationOperatorTest.)
     *
     * @dataProvider missingReasons
     */
    public function test_a_restore_without_a_usable_reason_writes_nothing(string $reason, string $refusal): void
    {
        $result = R::restore($this->wpdb, ['migratable'], $this->operator, $reason);

        $this->assertSame(R::OUTCOME_REFUSED, $result['outcome']);
        $this->assertSame($refusal, $result['reason']);
        $this->assertSame($this->afterMigration, $this->db->businessTables());
        $this->assertSame([], $this->db->transactionLog);
        $this->assertSame([], $this->db->locks);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function missingReasons(): array
    {
        return [
            'no reason' => ['  ', R::REFUSED_REASON_MISSING],
            'a reason too long' => [str_repeat('理', R::MAX_REASON_LENGTH + 1), R::REFUSED_REASON_TOO_LONG],
        ];
    }

    /**
     * @dataProvider failingSteps
     *
     * @param array<string, mixed> $failures
     * @param string[] $transactions
     */
    public function test_a_failing_step_rolls_the_whole_restore_back(array $failures, string $reason, array $transactions): void
    {
        $this->db->failures = $failures;

        $result = $this->restore(['migratable', 'migratable-2']);

        $this->assertSame(R::OUTCOME_ROLLED_BACK, $result['outcome']);
        $this->assertSame($reason, $result['reason']);
        $this->assertSame($transactions, $this->db->transactionLog);
        $this->assertSame($this->afterMigration, $this->db->businessTables());
        $this->assertStringContainsString('restore rolled back:', $this->logged());
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string[]}>
     */
    public static function failingSteps(): array
    {
        return [
            'the transaction does not start' => [['START' => true], R::FAILED_TRANSACTION_START, ['START']],
            'the expense lock' => [['LOCK' => 'expense:migratable-2'], R::FAILED_LOCK, ['START', 'ROLLBACK']],
            'the history lock' => [['LOCK' => 'expense_reviews:migratable'], R::FAILED_LOCK, ['START', 'ROLLBACK']],
            'the lines lock' => [['LOCK' => 'expense_lines:migratable-2'], R::FAILED_LOCK, ['START', 'ROLLBACK']],
            'the update' => [['RESTORE_UPDATE' => 'migratable-2'], R::FAILED_UPDATE, ['START', 'ROLLBACK']],
            'the history insert' => [['INSERT' => 'migratable-2'], R::FAILED_HISTORY_INSERT, ['START', 'ROLLBACK']],
            'the commit' => [['COMMIT' => 'migratable-2'], R::FAILED_COMMIT, ['START', 'COMMIT', 'ROLLBACK']],
        ];
    }

    /**
     * @dataProvider unusableRequests
     *
     * @param string[] $ids
     */
    public function test_nothing_is_written_without_ids_or_transactions(array $ids, ?string $engine, string $reason): void
    {
        $this->db->engines['stageart_expense_reviews'] = $engine;

        $result = $this->restore($ids);

        $this->assertSame(R::OUTCOME_REFUSED, $result['outcome']);
        $this->assertSame($reason, $result['reason']);
        $this->assertSame($this->afterMigration, $this->db->businessTables());
        $this->assertSame([], $this->db->transactionLog);
    }

    /**
     * @return array<string, array{string[], ?string, string}>
     */
    public static function unusableRequests(): array
    {
        return [
            'no ids' => [[' ', ''], 'InnoDB', R::REFUSED_NO_IDS],
            'MyISAM' => [['migratable'], 'MyISAM', R::REFUSED_NOT_TRANSACTIONAL],
            'unknown engine' => [['migratable'], null, R::REFUSED_NOT_TRANSACTIONAL],
        ];
    }

    public function test_no_journal_entry_is_locked_or_written(): void
    {
        $this->restore(['migratable', 'migratable-2']);

        foreach ($this->db->queries as $sql) {
            $this->assertStringNotContainsString('stageart_journal_entr', $sql, $sql);
            $this->assertStringNotContainsString('DELETE', $sql, 'No history - or anything else - is ever deleted.');
        }

        foreach ($this->db->locks as $lock) {
            $this->assertStringNotContainsString('journal_entr', $lock);
        }

        foreach (['insert', 'update', 'delete'] as $method) {
            $this->assertSame([], $this->wpdb->callsTo($method));
        }
    }

    public function test_the_log_carries_ids_and_reason_codes_only(): void
    {
        $this->restore(['migratable', 'missing']);
        $this->restore(['migratable']);

        $logged = $this->logged();

        foreach (['creator', 'manager', 'payer', '5000', '3000', '小道具', 'acc-', self::REASON, '判断誤り'] as $content) {
            $this->assertStringNotContainsString($content, $logged);
        }

        $this->assertStringContainsString('by person=' . self::ACTOR . ' wp_user=7 os_user=deploy', $logged);
    }
}
