<?php

declare(strict_types=1);

namespace StageArt\Tests\Infrastructure\WordPress\Persistence;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\ProjectId;
use StageArt\Infrastructure\WordPress\Persistence\WordPressProductionRepository;
use StageArt\Tests\Support\FakeWpdb;
use StageArt\Tests\Support\FakeWpdbDefault;

/**
 * PR #7 review (R4): the Production row lock - exclusive for Production
 * Completion and settlements, shared for bookings and reception - and a
 * Production save that can no longer fail silently.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ProductionLockTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../Support/wordpress-stubs.php';
    }

    public function test_the_exclusive_and_shared_locks_are_row_locks_on_the_production(): void
    {
        $wpdb = new \wpdb();
        $id = ProductionId::generate();
        $productions = new WordPressProductionRepository($wpdb);

        $this->assertNull($productions->findByIdForUpdate($id));
        $this->assertNull($productions->findByIdForShare($id));

        $this->assertSame([
            "SELECT * FROM wp_stageart_productions WHERE id = '{$id->toString()}' FOR UPDATE",
            "SELECT * FROM wp_stageart_productions WHERE id = '{$id->toString()}' LOCK IN SHARE MODE",
        ], array_map(static fn (array $args): string => (string) $args[0], $wpdb->callsTo('get_row')));
    }

    public function test_a_failed_production_lock_throws_instead_of_reporting_it_missing(): void
    {
        $wpdb = new \wpdb(static function (string $method, array $args, FakeWpdb $db) {
            if ($method === 'get_row') {
                $db->last_error = 'Lock wait timeout exceeded; try restarting transaction';

                return null;
            }

            return FakeWpdbDefault::VALUE;
        });
        $productions = new WordPressProductionRepository($wpdb);

        foreach (['findByIdForUpdate', 'findByIdForShare'] as $method) {
            try {
                $productions->{$method}(ProductionId::generate());
                $this->fail("{$method} returned on a failed lock.");
            } catch (RuntimeException $exception) {
                $this->assertSame('Failed to lock the Production. Lock wait timeout exceeded; try restarting transaction', $exception->getMessage());
            }
        }
    }

    public function test_a_failed_production_update_throws(): void
    {
        $wpdb = new \wpdb(static function (string $method, array $args, FakeWpdb $db) {
            if ($method === 'get_var') {
                return 'existing-id';
            }

            if ($method === 'update') {
                $db->last_error = 'Deadlock found when trying to get lock; try restarting transaction';

                return false;
            }

            return FakeWpdbDefault::VALUE;
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to update Production. Deadlock found');

        (new WordPressProductionRepository($wpdb))->save(Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate()));
    }

    public function test_a_failed_existence_check_is_not_taken_for_a_new_production(): void
    {
        $wpdb = new \wpdb(static function (string $method, array $args, FakeWpdb $db) {
            if ($method === 'get_var') {
                $db->last_error = 'MySQL server has gone away';

                return null;
            }

            return FakeWpdbDefault::VALUE;
        });

        try {
            (new WordPressProductionRepository($wpdb))->save(Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate()));
            $this->fail('Saved although the existence check failed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('MySQL server has gone away', $exception->getMessage());
        }

        $this->assertSame([], $wpdb->callsTo('insert'));
        $this->assertSame([], $wpdb->callsTo('update'));
    }
}
