<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Domain\Account\AccountId;
use StageArt\Domain\Budget\Budget;
use StageArt\Domain\Budget\BudgetLine;
use StageArt\Domain\Expense\Expense;
use StageArt\Domain\Expense\ExpenseLine;
use StageArt\Domain\JournalEntry\DebitCredit;
use StageArt\Domain\JournalEntry\JournalEntry;
use StageArt\Domain\JournalEntry\JournalEntryLine;
use StageArt\Domain\Organization\OrganizationId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Infrastructure\WordPress\Persistence\WordPressBudgetRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressExpenseRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressJournalEntryRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressTransactionManager;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * PR #7 review items 2 and 3: a Journal Entry, an Expense and a Budget are
 * each a header row plus line rows. Every one of those writes is checked -
 * $wpdb only returns false - and a failure throws, so the
 * TransactionManager::run() every saving Use Case goes through rolls back
 * the header and the lines together instead of committing half of them.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class AggregateWriteFailureTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function failures(): array
    {
        $cases = [];

        foreach (['journal entry' => ['journal_entries', 'journal_entry_lines'], 'expense' => ['expenses', 'expense_lines'], 'budget' => ['budgets', 'budget_lines']] as $aggregate => [$header, $lines]) {
            $cases["{$aggregate}: header insert"] = [$aggregate, 'insert', "wp_stageart_{$header}"];
            $cases["{$aggregate}: header update"] = [$aggregate, 'update', "wp_stageart_{$header}"];
            $cases["{$aggregate}: replacing the lines"] = [$aggregate, 'delete', "wp_stageart_{$lines}"];
            $cases["{$aggregate}: a line insert"] = [$aggregate, 'insert', "wp_stageart_{$lines}"];
        }

        return $cases;
    }

    /**
     * @dataProvider failures
     */
    public function test_a_failed_write_throws_and_rolls_the_whole_aggregate_back(string $aggregate, string $failingMethod, string $failingTable): void
    {
        $exists = $failingMethod === 'update';
        $wpdb = $this->wpdb($exists, $failingMethod, $failingTable);
        [$save] = $this->fixture($aggregate, $wpdb, $exists);

        try {
            (new WordPressTransactionManager($wpdb))->run($save);
            $this->fail('The failed write was reported as saved');
        } catch (RuntimeException $exception) {
            $this->assertStringStartsWith('Failed to', $exception->getMessage());
        }

        $statements = array_map(static fn (array $args): string => (string) $args[0], $wpdb->callsTo('query'));
        $this->assertSame(['START TRANSACTION', 'ROLLBACK'], $statements, 'Rolled back, never committed');
    }

    /**
     * @dataProvider aggregates
     */
    public function test_a_complete_save_commits_the_header_and_every_line(string $aggregate): void
    {
        $wpdb = $this->wpdb(false, null, null);
        [$save, $linesTable, $lineCount] = $this->fixture($aggregate, $wpdb, false);

        (new WordPressTransactionManager($wpdb))->run($save);

        $statements = array_map(static fn (array $args): string => (string) $args[0], $wpdb->callsTo('query'));
        $this->assertSame(['START TRANSACTION', 'COMMIT'], $statements);
        $lineInserts = array_filter($wpdb->callsTo('insert'), static fn (array $args): bool => $args[0] === $linesTable);
        $this->assertCount($lineCount, $lineInserts);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function aggregates(): array
    {
        return ['journal entry' => ['journal entry'], 'expense' => ['expense'], 'budget' => ['budget']];
    }

    /**
     * @param bool $exists whether the header row is already stored (save() then updates it)
     */
    private function wpdb(bool $exists, ?string $failingMethod, ?string $failingTable): FakeWpdb
    {
        return new \wpdb(static function (string $method, array $args) use ($exists, $failingMethod, $failingTable) {
            if ($method === 'get_var') {
                return $exists ? 'stored-id' : null;
            }

            if ($method === $failingMethod && ($args[0] ?? null) === $failingTable) {
                return false;
            }

            return FakeWpdbDefault::VALUE;
        });
    }

    /**
     * @param bool $exists whether the header row is already stored
     *
     * @return array{0: callable(): void, 1: string, 2: int} the save, the lines table, how many lines
     */
    private function fixture(string $aggregate, FakeWpdb $wpdb, bool $exists): array
    {
        $person = PersonId::generate();
        $production = ProductionId::generate();
        $account = AccountId::generate();

        switch ($aggregate) {
            case 'journal entry':
                $entry = JournalEntry::create(
                    OrganizationId::generate(),
                    $production,
                    new DateTimeImmutable('2026-11-09 10:00:00'),
                    '架空の仕訳',
                    [
                        JournalEntryLine::create($account, DebitCredit::debit(), 1500),
                        JournalEntryLine::create(AccountId::generate(), DebitCredit::credit(), 1500),
                    ],
                    $person
                );

                return [static fn () => (new WordPressJournalEntryRepository($wpdb))->save($entry), 'wp_stageart_journal_entry_lines', 2];
            case 'expense':
                $expense = Expense::create($production, [ExpenseLine::create($account, 800), ExpenseLine::create($account, 200)], $person);

                // The Expense repository chooses insert or update from the
                // Expense's own persisted state (PR #6), not from a SELECT.
                if ($exists) {
                    $expense->markPersisted();
                }

                return [static fn () => (new WordPressExpenseRepository($wpdb))->save($expense), 'wp_stageart_expense_lines', 2];
            default:
                $budget = Budget::create($production, '架空の予算', [BudgetLine::create($account, 30000), BudgetLine::create($account, 5000)], $person);

                return [static fn () => (new WordPressBudgetRepository($wpdb))->save($budget), 'wp_stageart_budget_lines', 2];
        }
    }
}
