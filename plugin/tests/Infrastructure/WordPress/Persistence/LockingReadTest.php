<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Domain\Reservation\ReservationId;
use StageArt\Infrastructure\WordPress\Persistence\WordPressExpenseRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressPerformanceRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressReservationRepository;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * PR #7 review items 4 and 7: the reads the capacity check and the Expense
 * confirmation rely on are locking reads - `FOR UPDATE` on the row that
 * serializes concurrent requests, and on the Reservations counted under
 * that lock, which therefore see every committed row. A locking read that
 * fails is never taken for "no rows": it throws.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class LockingReadTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
    }

    /**
     * @return string[] every SQL statement sent through get_row / get_results
     */
    private function reads(FakeWpdb $wpdb): array
    {
        return array_map(
            static fn (array $args): string => (string) $args[0],
            array_merge($wpdb->callsTo('get_row'), $wpdb->callsTo('get_results'))
        );
    }

    public function test_the_performance_is_read_with_a_row_lock(): void
    {
        $wpdb = new \wpdb();
        $id = PerformanceId::generate();

        $this->assertNull((new WordPressPerformanceRepository($wpdb))->findByIdForUpdate($id));

        $this->assertSame(["SELECT * FROM wp_stageart_performances WHERE id = '{$id->toString()}' FOR UPDATE"], $this->reads($wpdb));
    }

    public function test_the_performances_reservations_are_read_with_row_locks(): void
    {
        $wpdb = new \wpdb();
        $id = PerformanceId::generate();

        $this->assertSame([], (new WordPressReservationRepository($wpdb))->findByPerformanceIdForUpdate($id));

        $this->assertSame(["SELECT * FROM wp_stageart_reservations WHERE performance_id = '{$id->toString()}' ORDER BY created_at ASC FOR UPDATE"], $this->reads($wpdb));
    }

    public function test_the_expense_is_read_with_a_row_lock(): void
    {
        $wpdb = new \wpdb();
        $id = ExpenseId::generate();

        $this->assertNull((new WordPressExpenseRepository($wpdb))->findByIdForUpdate($id));

        $this->assertSame(["SELECT * FROM wp_stageart_expenses WHERE id = '{$id->toString()}' FOR UPDATE"], $this->reads($wpdb));
    }

    // --- a failed locking read is not "no rows" (PR #7 follow-up review) ---

    /**
     * $wpdb answers a failed query with no rows and only sets last_error.
     */
    private function failingLockingReads(): FakeWpdb
    {
        return new \wpdb(static function (string $method, array $args, FakeWpdb $db) {
            if (in_array($method, ['get_row', 'get_results'], true) && str_contains((string) $args[0], 'FOR UPDATE')) {
                $db->last_error = 'Lock wait timeout exceeded; try restarting transaction';

                return $method === 'get_row' ? null : [];
            }

            return FakeWpdbDefault::VALUE;
        });
    }

    public function test_a_failed_read_of_the_performances_reservations_throws_instead_of_returning_none(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to lock the Reservations of the Performance. Lock wait timeout exceeded');

        (new WordPressReservationRepository($this->failingLockingReads()))->findByPerformanceIdForUpdate(PerformanceId::generate());
    }

    public function test_a_failed_read_of_the_performance_throws_instead_of_returning_not_found(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to lock the Performance. Lock wait timeout exceeded');

        (new WordPressPerformanceRepository($this->failingLockingReads()))->findByIdForUpdate(PerformanceId::generate());
    }

    public function test_a_failed_read_of_a_reservation_throws_instead_of_returning_not_found(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to lock the Reservation. Lock wait timeout exceeded');

        (new WordPressReservationRepository($this->failingLockingReads()))->findByIdForUpdate(ReservationId::generate());
    }

    /**
     * A read that succeeds with no rows - last_error stays empty - is still
     * an empty result, not a failure.
     */
    public function test_a_successful_read_with_no_rows_is_still_empty(): void
    {
        $wpdb = new \wpdb();

        $this->assertSame([], (new WordPressReservationRepository($wpdb))->findByPerformanceIdForUpdate(PerformanceId::generate()));
        $this->assertNull((new WordPressReservationRepository($wpdb))->findByIdForUpdate(ReservationId::generate()));
        $this->assertNull((new WordPressPerformanceRepository($wpdb))->findByIdForUpdate(PerformanceId::generate()));
    }
}
