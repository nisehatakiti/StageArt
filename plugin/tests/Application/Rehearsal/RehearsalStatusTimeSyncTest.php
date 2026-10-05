<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Rehearsal;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Rehearsal\GetRehearsalQuery;
use StageArt\Application\Rehearsal\GetRehearsalUseCase;
use StageArt\Application\Rehearsal\ListRehearsalsForProductionQuery;
use StageArt\Application\Rehearsal\ListRehearsalsUseCase;
use StageArt\Application\Rehearsal\RehearsalAccessDeniedException;
use StageArt\Application\Rehearsal\RehearsalStatusTimeSync;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Rehearsal\RehearsalStatus;
use StageArt\Tests\Support\FakeIdentityContract;
use StageArt\Tests\Support\FakeMembershipContract;
use StageArt\Tests\Support\FakeProductionContextContract;
use StageArt\Tests\Support\InMemoryRehearsalRepository;

/**
 * StageArt 稽古管理ステータス自動同期 instruction: dedicated behavior tests for
 * RehearsalStatusTimeSync, covering the user's explicit 12-point test
 * matrix. These are separate from RehearsalUseCaseTest.php's own tests
 * (which exercise the full Rehearsal lifecycle and only incidentally
 * pass through sync) - this file asserts the sync rules themselves,
 * directly and through the two read UseCases that trigger it.
 */
final class RehearsalStatusTimeSyncTest extends TestCase
{
    private InMemoryRehearsalRepository $rehearsals;
    private RehearsalStatusTimeSync $statusTimeSync;

    protected function setUp(): void
    {
        $this->rehearsals = new InMemoryRehearsalRepository();
        $this->statusTimeSync = new RehearsalStatusTimeSync($this->rehearsals);
    }

    private function buildRehearsal(string $status, ?DateTimeImmutable $start, ?DateTimeImmutable $end): Rehearsal
    {
        $now = new DateTimeImmutable();

        return Rehearsal::reconstitute(
            RehearsalId::generate(),
            ProductionId::generate(),
            'Act 1 Run',
            null,
            $start,
            $end,
            'Asia/Tokyo',
            null,
            RehearsalStatus::fromString($status),
            $now,
            $now
        );
    }

    // 1. CONFIRMED before start -> stays CONFIRMED
    public function test_confirmed_before_start_stays_confirmed(): void
    {
        $rehearsal = $this->buildRehearsal(
            RehearsalStatus::CONFIRMED,
            new DateTimeImmutable('+1 hour'),
            new DateTimeImmutable('+2 hours')
        );
        $this->rehearsals->save($rehearsal);

        $synced = $this->statusTimeSync->sync($rehearsal);

        $this->assertSame(RehearsalStatus::CONFIRMED, $synced->status()->toString());
        $this->assertSame(RehearsalStatus::CONFIRMED, $this->rehearsals->findById($rehearsal->id())->status()->toString());
    }

    // 2. CONFIRMED at exact start -> ACTIVE
    public function test_confirmed_at_exact_start_becomes_active(): void
    {
        $start = new DateTimeImmutable();
        $rehearsal = $this->buildRehearsal(
            RehearsalStatus::CONFIRMED,
            $start,
            new DateTimeImmutable('+1 hour')
        );
        $this->rehearsals->save($rehearsal);

        // No sleep needed: real execution time between building $start and
        // calling sync() already guarantees now() >= $start by the time
        // sync() captures its own "now" - this is the "at exact start"
        // boundary (now >= start), not a strictly-after instant.
        $synced = $this->statusTimeSync->sync($rehearsal);

        $this->assertSame(RehearsalStatus::ACTIVE, $synced->status()->toString());
        $this->assertSame(RehearsalStatus::ACTIVE, $this->rehearsals->findById($rehearsal->id())->status()->toString());
    }

    // 3. CONFIRMED after start -> ACTIVE
    public function test_confirmed_after_start_becomes_active(): void
    {
        $rehearsal = $this->buildRehearsal(
            RehearsalStatus::CONFIRMED,
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable('+1 hour')
        );
        $this->rehearsals->save($rehearsal);

        $synced = $this->statusTimeSync->sync($rehearsal);

        $this->assertSame(RehearsalStatus::ACTIVE, $synced->status()->toString());
        $this->assertSame(RehearsalStatus::ACTIVE, $this->rehearsals->findById($rehearsal->id())->status()->toString());
    }

    // 4. ACTIVE before end -> stays ACTIVE
    public function test_active_before_end_stays_active(): void
    {
        $rehearsal = $this->buildRehearsal(
            RehearsalStatus::ACTIVE,
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable('+1 hour')
        );
        $this->rehearsals->save($rehearsal);

        $synced = $this->statusTimeSync->sync($rehearsal);

        $this->assertSame(RehearsalStatus::ACTIVE, $synced->status()->toString());
        $this->assertSame(RehearsalStatus::ACTIVE, $this->rehearsals->findById($rehearsal->id())->status()->toString());
    }

    // 5. ACTIVE at exact end -> COMPLETED
    public function test_active_at_exact_end_becomes_completed(): void
    {
        $end = new DateTimeImmutable();
        $rehearsal = $this->buildRehearsal(
            RehearsalStatus::ACTIVE,
            new DateTimeImmutable('-1 hour'),
            $end
        );
        $this->rehearsals->save($rehearsal);

        // Same "now >= end" boundary reasoning as the exact-start case above.
        $synced = $this->statusTimeSync->sync($rehearsal);

        $this->assertSame(RehearsalStatus::COMPLETED, $synced->status()->toString());
        $this->assertSame(RehearsalStatus::COMPLETED, $this->rehearsals->findById($rehearsal->id())->status()->toString());
    }

    // 6. ACTIVE after end -> COMPLETED
    public function test_active_after_end_becomes_completed(): void
    {
        $rehearsal = $this->buildRehearsal(
            RehearsalStatus::ACTIVE,
            new DateTimeImmutable('-2 hours'),
            new DateTimeImmutable('-1 hour')
        );
        $this->rehearsals->save($rehearsal);

        $synced = $this->statusTimeSync->sync($rehearsal);

        $this->assertSame(RehearsalStatus::COMPLETED, $synced->status()->toString());
        $this->assertSame(RehearsalStatus::COMPLETED, $this->rehearsals->findById($rehearsal->id())->status()->toString());
    }

    // 7. SCHEDULED after start -> stays SCHEDULED (no auto SCHEDULED->ACTIVE)
    public function test_scheduled_after_start_stays_scheduled(): void
    {
        $rehearsal = $this->buildRehearsal(
            RehearsalStatus::SCHEDULED,
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable('+1 hour')
        );
        $this->rehearsals->save($rehearsal);

        $synced = $this->statusTimeSync->sync($rehearsal);

        $this->assertSame(RehearsalStatus::SCHEDULED, $synced->status()->toString());
        $this->assertSame(RehearsalStatus::SCHEDULED, $this->rehearsals->findById($rehearsal->id())->status()->toString());
    }

    // 8. CANCELLED stays CANCELLED regardless of elapsed time
    public function test_cancelled_stays_cancelled_regardless_of_elapsed_time(): void
    {
        $rehearsal = $this->buildRehearsal(
            RehearsalStatus::CANCELLED,
            new DateTimeImmutable('-2 hours'),
            new DateTimeImmutable('-1 hour')
        );
        $this->rehearsals->save($rehearsal);

        $synced = $this->statusTimeSync->sync($rehearsal);

        $this->assertSame(RehearsalStatus::CANCELLED, $synced->status()->toString());
        $this->assertSame(RehearsalStatus::CANCELLED, $this->rehearsals->findById($rehearsal->id())->status()->toString());
    }

    // 9. List endpoint syncs needed Rehearsals in the DB.
    public function test_list_rehearsals_use_case_syncs_the_db(): void
    {
        [$listRehearsals, $productionId, $wordPressUserId] = $this->buildListRehearsalsUseCase();

        $confirmedPastStart = $this->buildRehearsal(
            RehearsalStatus::CONFIRMED,
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable('+1 hour')
        );
        $confirmedPastStart = $this->reassignToProduction($confirmedPastStart, $productionId);
        $this->rehearsals->save($confirmedPastStart);

        $results = $listRehearsals->execute(new ListRehearsalsForProductionQuery($productionId->toString(), $wordPressUserId));

        $this->assertCount(1, $results);
        $this->assertSame(RehearsalStatus::ACTIVE, $results[0]->status);
        // The DB row itself (not merely the response) was updated.
        $this->assertSame(
            RehearsalStatus::ACTIVE,
            $this->rehearsals->findById($confirmedPastStart->id())->status()->toString()
        );
    }

    // 10. Detail endpoint syncs the DB.
    // 11. Response status reflects the post-sync DB value.
    public function test_get_rehearsal_use_case_syncs_the_db_and_reflects_it_in_the_response(): void
    {
        [$getRehearsal, $productionId, $wordPressUserId] = $this->buildGetRehearsalUseCase();

        $activePastEnd = $this->buildRehearsal(
            RehearsalStatus::ACTIVE,
            new DateTimeImmutable('-2 hours'),
            new DateTimeImmutable('-1 hour')
        );
        $activePastEnd = $this->reassignToProduction($activePastEnd, $productionId);
        $this->rehearsals->save($activePastEnd);

        $result = $getRehearsal->execute(new GetRehearsalQuery($activePastEnd->id()->toString(), $wordPressUserId));

        $this->assertSame(RehearsalStatus::COMPLETED, $result->status);
        $this->assertSame(
            RehearsalStatus::COMPLETED,
            $this->rehearsals->findById($activePastEnd->id())->status()->toString()
        );
    }

    // 12. Existing permission checks remain unbroken: a non-member is still
    // denied, and denial happens without ever reaching (or needing) sync.
    public function test_get_rehearsal_use_case_still_denies_a_non_member_regardless_of_elapsed_time(): void
    {
        $productionId = ProductionId::generate();
        $memberId = PersonId::generate();
        $outsiderId = PersonId::generate();

        $productionContext = new FakeProductionContextContract();
        $productionContext->register($productionId, 'Show', 'ACTIVE');

        $identity = new FakeIdentityContract();
        $identity->register(1, $memberId);
        $identity->register(2, $outsiderId);

        $membership = new FakeMembershipContract();
        $membership->setMembers($productionId, [$memberId]);

        $getRehearsal = new GetRehearsalUseCase($this->rehearsals, $productionContext, $identity, $membership, $this->statusTimeSync);

        $confirmedPastStart = $this->buildRehearsal(
            RehearsalStatus::CONFIRMED,
            new DateTimeImmutable('-1 hour'),
            new DateTimeImmutable('+1 hour')
        );
        $confirmedPastStart = $this->reassignToProduction($confirmedPastStart, $productionId);
        $this->rehearsals->save($confirmedPastStart);

        $this->expectException(RehearsalAccessDeniedException::class);

        try {
            $getRehearsal->execute(new GetRehearsalQuery($confirmedPastStart->id()->toString(), 2));
        } finally {
            // Denial happens before sync runs - the DB row must be left
            // untouched, still CONFIRMED, not silently advanced to ACTIVE
            // for a request that was never authorized to see it.
            $this->assertSame(
                RehearsalStatus::CONFIRMED,
                $this->rehearsals->findById($confirmedPastStart->id())->status()->toString()
            );
        }
    }

    /**
     * @return array{0: ListRehearsalsUseCase, 1: ProductionId, 2: int}
     */
    private function buildListRehearsalsUseCase(): array
    {
        $productionId = ProductionId::generate();
        $personId = PersonId::generate();
        $wordPressUserId = 1;

        $productionContext = new FakeProductionContextContract();
        $productionContext->register($productionId, 'Show', 'ACTIVE');

        $identity = new FakeIdentityContract();
        $identity->register($wordPressUserId, $personId);

        $membership = new FakeMembershipContract();
        $membership->setMembers($productionId, [$personId]);

        $listRehearsals = new ListRehearsalsUseCase($this->rehearsals, $productionContext, $identity, $membership, $this->statusTimeSync);

        return [$listRehearsals, $productionId, $wordPressUserId];
    }

    /**
     * @return array{0: GetRehearsalUseCase, 1: ProductionId, 2: int}
     */
    private function buildGetRehearsalUseCase(): array
    {
        $productionId = ProductionId::generate();
        $personId = PersonId::generate();
        $wordPressUserId = 1;

        $productionContext = new FakeProductionContextContract();
        $productionContext->register($productionId, 'Show', 'ACTIVE');

        $identity = new FakeIdentityContract();
        $identity->register($wordPressUserId, $personId);

        $membership = new FakeMembershipContract();
        $membership->setMembers($productionId, [$personId]);

        $getRehearsal = new GetRehearsalUseCase($this->rehearsals, $productionContext, $identity, $membership, $this->statusTimeSync);

        return [$getRehearsal, $productionId, $wordPressUserId];
    }

    private function reassignToProduction(Rehearsal $rehearsal, ProductionId $productionId): Rehearsal
    {
        return Rehearsal::reconstitute(
            $rehearsal->id(),
            $productionId,
            $rehearsal->title(),
            $rehearsal->description(),
            $rehearsal->startDateTime(),
            $rehearsal->endDateTime(),
            $rehearsal->timezone(),
            $rehearsal->location(),
            $rehearsal->status(),
            $rehearsal->createdAt(),
            $rehearsal->updatedAt()
        );
    }
}
