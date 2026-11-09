<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Expense\ExpenseId;
use StageArt\Domain\Performance\PerformanceId;
use StageArt\Infrastructure\WordPress\Persistence\WordPressExpenseRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressPerformanceRepository;
use StageArt\Infrastructure\WordPress\Persistence\WordPressReservationRepository;
use StageArt\Tests\Support\FakeWpdb;

/**
 * PR #7 review items 4 and 7: the reads the capacity check and the Expense
 * confirmation rely on are locking reads - `FOR UPDATE` on the row that
 * serializes concurrent requests, and on the Reservations counted under
 * that lock, which therefore see every committed row.
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
}
