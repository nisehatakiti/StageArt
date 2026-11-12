<?php

declare(strict_types=1);

namespace StageArt\Tests\Core;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Application\Production\ProductionClosedException;
use StageArt\Application\Production\ProductionNotFoundException;
use StageArt\Core\Adapter\CoreProductionActivityLockAdapter;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Production\ProductionStatus;
use StageArt\Domain\Project\ProjectId;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\ProductionStatusFixture;

/**
 * PR #7 review (R4): the Production lock bookings and reception take
 * first - shared mode, and only on a Production still open for business.
 */
final class CoreProductionActivityLockAdapterTest extends TestCase
{
    private InMemoryProductionRepository $productions;
    private CoreProductionActivityLockAdapter $lock;

    protected function setUp(): void
    {
        $this->productions = new InMemoryProductionRepository();
        $this->lock = new CoreProductionActivityLockAdapter($this->productions);
    }

    private function production(string $status): Production
    {
        $production = Production::create(ProjectId::generate(), new ProductionName('Show'), PersonId::generate());
        ProductionStatusFixture::force($production, $status);
        $this->productions->save($production);

        return $production;
    }

    public function test_an_open_production_is_locked_in_shared_mode(): void
    {
        foreach ([ProductionStatus::PLANNING, ProductionStatus::ACTIVE] as $status) {
            $production = $this->production($status);

            $this->lock->lockOpenProduction($production->id());

            $this->assertSame(['SHARE', $production->id()->toString()], end($this->productions->locks), $status);
        }
    }

    public function test_a_completed_or_archived_production_is_closed(): void
    {
        foreach ([ProductionStatus::COMPLETED, ProductionStatus::ARCHIVED] as $status) {
            $production = $this->production($status);

            try {
                $this->lock->lockOpenProduction($production->id());
                $this->fail("{$status} was not refused.");
            } catch (ProductionClosedException $exception) {
                $this->assertStringContainsString($production->id()->toString(), $exception->getMessage());
            }
        }
    }

    public function test_a_missing_production_is_not_found(): void
    {
        $this->expectException(ProductionNotFoundException::class);

        $this->lock->lockOpenProduction(ProductionId::generate());
    }

    public function test_a_failed_lock_is_not_taken_for_an_open_production(): void
    {
        $production = $this->production(ProductionStatus::ACTIVE);
        $this->productions->lockFailure = new RuntimeException('Failed to lock the Production. Lock wait timeout exceeded; try restarting transaction');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Lock wait timeout exceeded');

        $this->lock->lockOpenProduction($production->id());
    }

    // --- PR #7 review (R5) ---

    public function test_a_cancelled_production_is_closed_for_new_bookings_and_reception(): void
    {
        $production = $this->production(ProductionStatus::CANCELLED);

        try {
            $this->lock->lockOpenProduction($production->id());
            $this->fail('CANCELLED was not refused.');
        } catch (ProductionClosedException $exception) {
            $this->assertSame(ProductionStatus::CANCELLED, $exception->productionStatus());
            $this->assertStringContainsString('CANCELLED', $exception->getMessage());
        }
    }

    public function test_a_cancelled_production_can_still_wind_its_bookings_down(): void
    {
        foreach ([ProductionStatus::PLANNING, ProductionStatus::ACTIVE, ProductionStatus::CANCELLED] as $status) {
            $production = $this->production($status);

            $this->lock->lockUnsettledProduction($production->id());

            $this->assertSame(['SHARE', $production->id()->toString()], end($this->productions->locks), $status);
        }
    }

    public function test_nothing_is_taken_back_once_the_settlement_is_closed(): void
    {
        foreach ([ProductionStatus::COMPLETED, ProductionStatus::ARCHIVED] as $status) {
            $production = $this->production($status);

            try {
                $this->lock->lockUnsettledProduction($production->id());
                $this->fail("{$status} was not refused.");
            } catch (ProductionClosedException $exception) {
                $this->assertSame($status, $exception->productionStatus());
            }
        }
    }

    /**
     * (R6: the status-free shared lock, used only by the Performance
     * cancellation, was removed when that cancellation started refusing
     * COMPLETED / ARCHIVED.)
     */
    public function test_the_plain_exclusive_lock_takes_any_status(): void
    {
        foreach ([ProductionStatus::ACTIVE, ProductionStatus::CANCELLED, ProductionStatus::COMPLETED, ProductionStatus::ARCHIVED] as $status) {
            $production = $this->production($status);

            $this->lock->lockProductionExclusively($production->id());
            $this->assertSame(['FOR UPDATE', $production->id()->toString()], end($this->productions->locks), $status);
        }
    }

    public function test_the_exclusive_lock_for_a_performance_edit_refuses_a_closed_settlement_only(): void
    {
        foreach ([ProductionStatus::PLANNING, ProductionStatus::ACTIVE, ProductionStatus::CANCELLED] as $status) {
            $production = $this->production($status);

            $this->lock->lockUnsettledProductionExclusively($production->id());
            $this->assertSame(['FOR UPDATE', $production->id()->toString()], end($this->productions->locks), $status);
        }

        foreach ([ProductionStatus::COMPLETED, ProductionStatus::ARCHIVED] as $status) {
            $production = $this->production($status);

            try {
                $this->lock->lockUnsettledProductionExclusively($production->id());
                $this->fail("{$status} was not refused.");
            } catch (ProductionClosedException $exception) {
                $this->assertSame($status, $exception->productionStatus());
            }
        }
    }

    public function test_every_lock_reports_a_missing_production(): void
    {
        foreach (['lockUnsettledProduction', 'lockUnsettledProductionExclusively', 'lockProductionExclusively'] as $method) {
            try {
                $this->lock->{$method}(ProductionId::generate());
                $this->fail("{$method} took a lock on nothing.");
            } catch (ProductionNotFoundException $exception) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * The status is the one read under the lock: a completion committed
     * while this request waited for the lock is seen.
     */
    public function test_a_completion_committed_while_waiting_for_the_lock_is_seen(): void
    {
        $production = $this->production(ProductionStatus::ACTIVE);
        $this->productions->beforeLockGranted = static function () use ($production): void {
            ProductionStatusFixture::force($production, ProductionStatus::COMPLETED);
        };

        $this->expectException(ProductionClosedException::class);

        $this->lock->lockOpenProduction($production->id());
    }
}
