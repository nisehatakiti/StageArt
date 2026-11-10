<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Domain\Reservation\ReservationNumber;
use StageArt\Domain\Ticket\TicketId;
use StageArt\Infrastructure\WordPress\Persistence\WordPressExpenseRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressPerformanceRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressReservationRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressSettlementRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressTicketRepository;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * PR #7 review: the plain (non-locking) reads the settlement, Production
 * Completion and the Expense workflow act on never take a failed query for
 * "no rows". $wpdb answers a failed read with null / an empty array and
 * only sets last_error; these repositories throw then, so a DB outage can
 * no longer pass for "this Production has no Reservations" (settlement
 * completed on nothing) or "this Expense does not exist". A read that
 * succeeds with no rows is still empty / null.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ReadFailureTest extends TestCase
{
    private const ERROR = 'MySQL server has gone away';

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
    }

    /**
     * Every read fails as $wpdb reports it: no rows, last_error set.
     */
    private function failingReads(): FakeWpdb
    {
        return new \wpdb(static function (string $method, array $args, FakeWpdb $db) {
            if (in_array($method, ['get_row', 'get_results'], true)) {
                $db->last_error = self::ERROR;

                return $method === 'get_row' ? null : [];
            }

            return FakeWpdbDefault::VALUE;
        });
    }

    /**
     * @param array<string, callable(): mixed> $reads
     */
    private function assertEachThrows(array $reads): void
    {
        foreach ($reads as $name => $read) {
            try {
                $read();
                $this->fail("{$name} returned instead of throwing on a DB error.");
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString(self::ERROR, $exception->getMessage(), $name);
                $this->assertStringStartsWith('Failed to ', $exception->getMessage(), $name);
            }
        }
    }

    /**
     * @return array<string, callable(): mixed>
     */
    private function settlementReads(\wpdb $wpdb): array
    {
        $reservations = new WordPressReservationRepository($wpdb);
        $performances = new WordPressPerformanceRepository($wpdb);
        $tickets = new WordPressTicketRepository($wpdb);
        $settlements = new WordPressSettlementRepository($wpdb);

        return [
            'Reservation findByPerformanceId' => static fn () => $reservations->findByPerformanceId(PerformanceId::generate()),
            'Reservation findById' => static fn () => $reservations->findById(ReservationId::generate()),
            'Reservation findByReservationNumber' => static fn () => $reservations->findByReservationNumber(ReservationNumber::generate()),
            'Reservation findByIds' => static fn () => $reservations->findByIds([ReservationId::generate()]),
            'Performance findByProductionId' => static fn () => $performances->findByProductionId(ProductionId::generate()),
            'Performance findById' => static fn () => $performances->findById(PerformanceId::generate()),
            'Performance findByIds' => static fn () => $performances->findByIds([PerformanceId::generate()]),
            'Performance findByProductionAndDateTime' => static fn () => $performances->findByProductionAndDateTime(ProductionId::generate(), new \DateTimeImmutable('2026-12-01'), '18:00:00'),
            'Performance findByProductionAndDateTime (excluding one)' => static fn () => $performances->findByProductionAndDateTime(ProductionId::generate(), new \DateTimeImmutable('2026-12-01'), '18:00:00', PerformanceId::generate()),
            'Ticket findByProductionId' => static fn () => $tickets->findByProductionId(ProductionId::generate()),
            'Ticket findById' => static fn () => $tickets->findById(TicketId::generate()),
            'Ticket findByIds' => static fn () => $tickets->findByIds([TicketId::generate()]),
            'Settlement findByProductionAndPerson' => static fn () => $settlements->findByProductionAndPerson(ProductionId::generate(), PersonId::generate()),
            'Settlement findByProductionId' => static fn () => $settlements->findByProductionId(ProductionId::generate()),
        ];
    }

    public function test_a_failed_read_of_the_settlement_inputs_throws_instead_of_returning_none(): void
    {
        $this->assertEachThrows($this->settlementReads($this->failingReads()));
    }

    public function test_the_reservations_of_a_performance_are_not_reported_as_none_on_a_db_error(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to read the Reservations of the Performance. ' . self::ERROR);

        (new WordPressReservationRepository($this->failingReads()))->findByPerformanceId(PerformanceId::generate());
    }

    public function test_a_successful_read_with_no_rows_is_still_empty_or_null(): void
    {
        $wpdb = new \wpdb();

        foreach ($this->settlementReads($wpdb) as $name => $read) {
            $result = $read();
            $this->assertTrue($result === [] || $result === null, "{$name} on no rows");
        }
    }

    // --- Expense (PR #6 workflow) ---

    public function test_a_failed_read_of_an_expense_throws_instead_of_reporting_it_missing(): void
    {
        $expenses = new WordPressExpenseRepository($this->failingReads());

        $this->assertEachThrows([
            'Expense findById' => static fn () => $expenses->findById(ExpenseId::generate()),
            'Expense findByIdForUpdate' => static fn () => $expenses->findByIdForUpdate(ExpenseId::generate()),
            'Expense findByProductionId' => static fn () => $expenses->findByProductionId(ProductionId::generate()),
        ]);
    }

    /**
     * A lock wait timeout (1205) or a deadlock (1213) on the row lock is
     * neither "not found" nor a successful read.
     */
    public function test_a_failed_row_lock_on_an_expense_is_not_reported_as_not_found(): void
    {
        $wpdb = new \wpdb(static function (string $method, array $args, FakeWpdb $db) {
            if ($method === 'get_row' && str_contains((string) $args[0], 'FOR UPDATE')) {
                $db->last_error = 'Lock wait timeout exceeded; try restarting transaction';

                return null;
            }

            return FakeWpdbDefault::VALUE;
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to lock the Expense. Lock wait timeout exceeded');

        (new WordPressExpenseRepository($wpdb))->findByIdForUpdate(ExpenseId::generate());
    }

    /**
     * The Expense row was read, but its Lines (or history) were not: the
     * Expense must not be built with no Lines / no history.
     *
     * @return array<string, mixed>
     */
    private function expenseRow(string $id): array
    {
        return [
            'id' => $id,
            'production_id' => ProductionId::generate()->toString(),
            'status' => 'SUBMITTED',
            'created_by' => PersonId::generate()->toString(),
            'created_at' => '2026-11-01 10:00:00',
            'updated_by' => PersonId::generate()->toString(),
            'updated_at' => '2026-11-01 10:00:00',
            'revision' => 1,
        ];
    }

    public function test_an_expense_whose_lines_could_not_be_read_is_not_returned_without_lines(): void
    {
        $id = ExpenseId::generate()->toString();
        $row = $this->expenseRow($id);
        $wpdb = new \wpdb(static function (string $method, array $args, FakeWpdb $db) use ($row) {
            if ($method === 'get_row') {
                return $row;
            }

            if ($method === 'get_results' && str_contains((string) $args[0], 'stageart_expense_lines')) {
                $db->last_error = self::ERROR;

                return [];
            }

            return FakeWpdbDefault::VALUE;
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to read the Expense Lines. ' . self::ERROR);

        (new WordPressExpenseRepository($wpdb))->findByIdForUpdate(ExpenseId::fromString($id));
    }

    public function test_an_expense_whose_history_could_not_be_read_is_not_returned_without_history(): void
    {
        $id = ExpenseId::generate()->toString();
        $row = $this->expenseRow($id);
        $wpdb = new \wpdb(static function (string $method, array $args, FakeWpdb $db) use ($row) {
            if ($method === 'get_row') {
                return $row;
            }

            if ($method === 'get_results' && str_contains((string) $args[0], 'stageart_expense_reviews')) {
                $db->last_error = self::ERROR;

                return [];
            }

            return FakeWpdbDefault::VALUE;
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to read the Expense history. ' . self::ERROR);

        (new WordPressExpenseRepository($wpdb))->findById(ExpenseId::fromString($id));
    }

    public function test_an_expense_that_does_not_exist_is_still_null(): void
    {
        $expenses = new WordPressExpenseRepository(new \wpdb());

        $this->assertNull($expenses->findById(ExpenseId::generate()));
        $this->assertNull($expenses->findByIdForUpdate(ExpenseId::generate()));
        $this->assertSame([], $expenses->findByProductionId(ProductionId::generate()));
    }
}
