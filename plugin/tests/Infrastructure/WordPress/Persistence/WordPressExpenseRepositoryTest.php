<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Application\Expense\ExpenseResult;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Expense\ExpenseLine;
use StageArt\Domain\Expense\ExpenseLineId;
use StageArt\Domain\Expense\ExpenseStateConflictException;
use StageArt\Domain\Expense\ExpenseStatus;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Infrastructure\WordPress\Persistence\WordPressExpenseRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressJournalEntryRepository;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * 修正8: the WordPress repositories' write rules, against a scripted
 * $wpdb - conditional status / revision update, conflict detection,
 * append-only history, row lock, and "a failed write throws" (so the
 * transaction rolls back) for Expense and Journal Entry alike.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class WordPressExpenseRepositoryTest extends TestCase
{
    /** @var array<string, mixed> scripted answers, see wpdb() */
    private array $answers = [];

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
    }

    /**
     * $answers keys: 'insert:<table>', 'update:<table>', 'delete:<table>'
     * (return value), 'get_row' (row for SELECT status, revision).
     */
    private function wpdb(): FakeWpdb
    {
        return new \wpdb(function (string $method, array $args) {
            $key = in_array($method, ['insert', 'update', 'delete'], true) ? "{$method}:{$args[0]}" : $method;

            return array_key_exists($key, $this->answers) ? $this->answers[$key] : FakeWpdbDefault::VALUE;
        });
    }

    private function loadedSubmitted(int $revision = 1): Expense
    {
        $now = new DateTimeImmutable('2026-10-01 09:00:00');
        $applicant = PersonId::generate();

        return Expense::reconstitute(
            ExpenseId::generate(),
            ProductionId::generate(),
            null,
            ExpenseStatus::fromString(ExpenseStatus::SUBMITTED),
            [ExpenseLine::create(AccountId::generate(), 5000)],
            $applicant,
            $now,
            $applicant,
            $now,
            null,
            null,
            $revision,
            $applicant,
            $now,
            5000
        );
    }

    public function test_a_new_expense_is_inserted_with_its_lines_and_history(): void
    {
        $wpdb = $this->wpdb();
        $expense = Expense::create(ProductionId::generate(), [ExpenseLine::create(AccountId::generate(), 1200)], PersonId::generate());
        $expense->submit($expense->createdBy());

        (new WordPressExpenseRepository($wpdb))->save($expense);

        $inserts = $wpdb->callsTo('insert');
        $this->assertSame(['wp_stageart_expenses', 'wp_stageart_expense_lines', 'wp_stageart_expense_reviews'], array_column($inserts, 0));
        $this->assertSame('SUBMITTED', $inserts[0][1]['status']);
        $this->assertSame(1, $inserts[0][1]['revision']);
        $this->assertSame(1200, $inserts[0][1]['requested_amount']);
        $this->assertSame(['revision' => 1, 'action' => 'SUBMITTED', 'requested_amount' => 1200], array_intersect_key($inserts[2][1], array_flip(['revision', 'action', 'requested_amount'])));
        $this->assertSame([], $wpdb->callsTo('update'));
        $this->assertSame('SUBMITTED', $expense->persistedStatus());
        $this->assertSame([], $expense->pendingReviews());
    }

    public function test_an_update_is_conditional_on_the_loaded_status_and_revision(): void
    {
        $wpdb = $this->wpdb();
        $expense = $this->loadedSubmitted(3);
        $expense->reject(PersonId::generate(), '不備あり');

        (new WordPressExpenseRepository($wpdb))->save($expense);

        $update = $wpdb->callsTo('update')[0];
        $this->assertSame('wp_stageart_expenses', $update[0]);
        $this->assertSame('REJECTED', $update[1]['status']);
        $this->assertSame('不備あり', $update[1]['rejection_reason']);
        $this->assertSame(['id' => $expense->id()->toString(), 'status' => 'SUBMITTED', 'revision' => 3], $update[2]);
        $this->assertSame('REJECTED', $expense->persistedStatus());
    }

    public function test_an_update_that_matches_no_row_because_someone_else_changed_it_is_a_conflict(): void
    {
        $this->answers = ['update:wp_stageart_expenses' => 0, 'get_row' => ['status' => 'POSTED', 'revision' => '1']];
        $wpdb = $this->wpdb();
        $expense = $this->loadedSubmitted();
        $expense->reject(PersonId::generate(), '不備あり');

        try {
            (new WordPressExpenseRepository($wpdb))->save($expense);
            $this->fail('A conditional update that matched nothing must be a conflict.');
        } catch (ExpenseStateConflictException $exception) {
            $this->assertSame([], $wpdb->callsTo('insert'), 'No line or history row is written after a conflict');
            $this->assertSame([], $wpdb->callsTo('delete'));
            $this->assertSame('SUBMITTED', $expense->persistedStatus());
        }
    }

    public function test_an_update_that_matched_but_changed_nothing_is_not_a_conflict(): void
    {
        $this->answers = ['update:wp_stageart_expenses' => 0, 'get_row' => ['status' => 'DRAFT', 'revision' => '0']];
        $wpdb = $this->wpdb();
        $now = new DateTimeImmutable();
        $creator = PersonId::generate();
        $expense = Expense::reconstitute(
            ExpenseId::generate(),
            ProductionId::generate(),
            null,
            ExpenseStatus::draft(),
            [ExpenseLine::create(AccountId::generate(), 1000)],
            $creator,
            $now,
            $creator,
            $now,
            null,
            null
        );
        $expense->replaceLines([ExpenseLine::create(AccountId::generate(), 1000)], $creator);

        (new WordPressExpenseRepository($wpdb))->save($expense);

        $this->assertCount(1, $wpdb->callsTo('update'));
    }

    /**
     * @dataProvider failingWrites
     */
    public function test_a_failed_write_throws_so_the_transaction_rolls_back(string $answerKey): void
    {
        $this->answers = [$answerKey => false];
        $wpdb = $this->wpdb();
        $expense = $this->loadedSubmitted();
        $expense->reject(PersonId::generate(), '不備あり');

        $this->expectException(RuntimeException::class);

        (new WordPressExpenseRepository($wpdb))->save($expense);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function failingWrites(): array
    {
        return [
            'expense update' => ['update:wp_stageart_expenses'],
            'line delete' => ['delete:wp_stageart_expense_lines'],
            'line insert' => ['insert:wp_stageart_expense_lines'],
            'history insert (e.g. a second REJECTED for the revision)' => ['insert:wp_stageart_expense_reviews'],
        ];
    }

    public function test_find_for_update_locks_the_row(): void
    {
        $wpdb = $this->wpdb();

        (new WordPressExpenseRepository($wpdb))->findByIdForUpdate(ExpenseId::generate());

        $sql = (string) $wpdb->callsTo('get_row')[0][0];
        $this->assertStringContainsString('FROM wp_stageart_expenses WHERE id =', $sql);
        $this->assertStringEndsWith('FOR UPDATE', $sql);
    }

    public function test_a_row_stored_before_the_workflow_columns_still_loads(): void
    {
        $id = ExpenseId::generate()->toString();
        $person = PersonId::generate()->toString();
        $this->answers = ['get_row' => [
            'id' => $id,
            'production_id' => ProductionId::generate()->toString(),
            'payer_person_id' => null,
            'status' => 'CONFIRMED',
            'created_by' => $person,
            'created_at' => '2026-09-01 10:00:00',
            'updated_by' => $person,
            'updated_at' => '2026-09-01 10:00:00',
            'confirmed_by' => $person,
            'confirmed_at' => '2026-09-02 10:00:00',
        ]];

        $expense = (new WordPressExpenseRepository($this->wpdb()))->findById(ExpenseId::fromString($id));

        $this->assertSame(ExpenseStatus::CONFIRMED, $expense->status()->toString());
        $this->assertSame(0, $expense->revision());
        $this->assertNull($expense->requestedAmount());
        $this->assertNull($expense->postedJournalEntryId());
        $this->assertSame(ExpenseStatus::CONFIRMED, $expense->persistedStatus());
    }

    /**
     * Version 1.8: an Expense whose legacy migration was undone loads with
     * both history entries - MIGRATED and MIGRATION_REVERTED - read in time
     * order, and the API result lists both.
     */
    public function test_a_reverted_migration_loads_with_both_history_entries_in_time_order(): void
    {
        $id = ExpenseId::generate()->toString();
        $person = PersonId::generate()->toString();
        $operator = PersonId::generate()->toString();
        $account = AccountId::generate()->toString();
        $snapshot = (string) json_encode([['account_id' => $account, 'amount' => 5000, 'description' => '小道具']], JSON_UNESCAPED_UNICODE);
        $reviewSql = null;

        $wpdb = new \wpdb(static function (string $method, array $args) use ($id, $person, $operator, $account, $snapshot, &$reviewSql) {
            $sql = (string) ($args[0] ?? '');

            if ($method === 'get_row') {
                return [
                    'id' => $id, 'production_id' => ProductionId::generate()->toString(), 'payer_person_id' => null,
                    'status' => 'CONFIRMED', 'created_by' => $person, 'created_at' => '2026-09-01 10:00:00',
                    'updated_by' => $person, 'updated_at' => '2026-09-01 10:00:00',
                    'confirmed_by' => $person, 'confirmed_at' => '2026-09-02 10:00:00', 'revision' => '0',
                ];
            }

            if ($method === 'get_results' && str_contains($sql, 'stageart_expense_reviews')) {
                $reviewSql = $sql;

                return [
                    ['revision' => '1', 'action' => 'MIGRATED', 'requested_amount' => '5000', 'approved_amount' => '5000', 'lines_snapshot' => $snapshot, 'reason' => null, 'actor_person_id' => $person, 'acted_at' => '2026-09-02 10:00:00'],
                    ['revision' => '1', 'action' => 'MIGRATION_REVERTED', 'requested_amount' => '5000', 'approved_amount' => '5000', 'lines_snapshot' => $snapshot, 'reason' => '判断誤り', 'actor_person_id' => $operator, 'acted_at' => '2026-11-01 09:00:00'],
                ];
            }

            if ($method === 'get_results') {
                return [['id' => ExpenseLineId::generate()->toString(), 'account_id' => $account, 'amount' => '5000', 'description' => '小道具']];
            }

            return FakeWpdbDefault::VALUE;
        });

        $expense = (new WordPressExpenseRepository($wpdb))->findById(ExpenseId::fromString($id));
        $reviews = ExpenseResult::fromDomain($expense)->toArray()['reviews'];

        $this->assertStringContainsString('ORDER BY acted_at ASC, revision ASC', (string) $reviewSql);
        $this->assertSame(ExpenseStatus::CONFIRMED, $expense->status()->toString());
        $this->assertSame(['MIGRATED', 'MIGRATION_REVERTED'], array_column($reviews, 'action'));
        $this->assertSame([$person, $operator], array_column($reviews, 'actor_person_id'));
        $this->assertSame([null, '判断誤り'], array_column($reviews, 'reason'));
        $this->assertSame([5000, 5000], array_column($reviews, 'requested_amount'));
    }

    /**
     * @dataProvider failingJournalEntryWrites
     */
    public function test_a_failed_journal_entry_write_throws(string $answerKey): void
    {
        $this->answers = [$answerKey => false];
        $person = PersonId::generate();
        $entry = JournalEntry::create(
            OrganizationId::generate(),
            ProductionId::generate(),
            new DateTimeImmutable(),
            'Expense Approved',
            [
                JournalEntryLine::create(AccountId::generate(), DebitCredit::debit(), 100),
                JournalEntryLine::create(AccountId::generate(), DebitCredit::credit(), 100),
            ],
            $person,
            'ExpenseApproved',
            ExpenseId::generate()->toString()
        );

        $this->expectException(RuntimeException::class);

        (new WordPressJournalEntryRepository($this->wpdb()))->save($entry);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function failingJournalEntryWrites(): array
    {
        return [
            'entry insert' => ['insert:wp_stageart_journal_entries'],
            'line delete' => ['delete:wp_stageart_journal_entry_lines'],
            'line insert' => ['insert:wp_stageart_journal_entry_lines'],
        ];
    }
}
