<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Settlement;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Settlement\CancelProductionMemberSettlementCommand;
use StageArt\Application\Settlement\CancelProductionMemberSettlementUseCase;
use StageArt\Application\Settlement\GetProductionSettlementSummaryQuery;
use StageArt\Application\Settlement\GetProductionSettlementSummaryUseCase;
use StageArt\Application\Settlement\NothingToCancelException;
use StageArt\Application\Settlement\NothingToSettleException;
use StageArt\Application\Settlement\ProductionSettlementCalculator;
use StageArt\Application\Settlement\SettleProductionMemberCommand;
use StageArt\Application\Settlement\SettleProductionMemberUseCase;
use StageArt\Application\Settlement\SettlementAccessDeniedException;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Performance\Performance;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Reservation\Reservation;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Ticket\Ticket;
use StageArt\Domain\Ticket\TicketBackMode;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPerformanceRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryReservationRepository;
use StageArt\Tests\Support\InMemorySettlementRepository;
use StageArt\Tests\Support\InMemoryTicketRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;
use StageArt\Tests\Support\ReadFailingReservationRepository;

final class SettlementUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryPerformanceRepository $performances;
    private InMemoryReservationRepository $reservations;
    private InMemoryTicketRepository $tickets;
    private InMemorySettlementRepository $settlements;
    private InMemoryProductionDelegateRepository $delegates;

    private CoreIdentityAdapter $identity;
    private CoreAuthorizationAdapter $authorization;
    private CoreMembershipAdapter $membershipContract;

    private GetProductionSettlementSummaryUseCase $getSummary;
    private SettleProductionMemberUseCase $settleMember;
    private CancelProductionMemberSettlementUseCase $cancelSettlement;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->performances = new InMemoryPerformanceRepository();
        $this->reservations = new InMemoryReservationRepository();
        $this->tickets = new InMemoryTicketRepository();
        $this->settlements = new InMemorySettlementRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $this->delegates = new InMemoryProductionDelegateRepository();
        $participants = new InMemoryParticipantRepository();
        $productionAuthorization = new ProductionAuthorizationService($organizationAuthorization, $this->delegates, $participants);
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);
        $membershipContract = new CoreMembershipAdapter($participants, $this->productions, $this->people, $productionAuthorization);
        $calculator = new ProductionSettlementCalculator($this->performances, $this->reservations, $this->tickets);
        $this->identity = $identity;
        $this->authorization = $authorization;
        $this->membershipContract = $membershipContract;

        $this->getSummary = new GetProductionSettlementSummaryUseCase(
            $this->productions,
            $membershipContract,
            $this->people,
            $this->settlements,
            $calculator,
            $identity,
            $authorization
        );
        $this->settleMember = new SettleProductionMemberUseCase(
            $this->productions,
            $this->settlements,
            $calculator,
            $identity,
            $authorization,
            new InMemoryTransactionManager()
        );
        $this->cancelSettlement = new CancelProductionMemberSettlementUseCase(
            $this->productions,
            $this->settlements,
            $identity,
            $authorization,
            new InMemoryTransactionManager()
        );
    }

    /**
     * @return array{0: Production, 1: \StageArt\Domain\Person\Person}
     */
    private function givenProductionWithOneMemberSale(): array
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create(1);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');
        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $production->changeCapacity(20);
        $production->updateTicketBack(
            TicketBackMode::PROGRESSIVE,
            json_encode([['priority' => 1, 'threshold' => 1, 'comparator' => 'GTE', 'rate_percent' => 10]])
        );
        $this->productions->save($production);

        $performance = Performance::create($production->id(), new DateTimeImmutable('-1 day'), '18:00', null, 20, null, null);
        $this->performances->save($performance);

        $ticket = Ticket::create($production->id(), '一般', 3000, null);
        $this->tickets->save($ticket);

        $member = Person::create(2);
        $this->people->save($member);

        $reservation = Reservation::create($performance->id(), $ticket->id(), 'A', 'a@example.com', 1, 3000, null, $member->id());
        $reservation->checkIn(null);
        $this->reservations->save($reservation);

        return [$production, $member];
    }

    public function test_summary_shows_confirmed_and_outstanding_amounts(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();

        $summary = $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 1));

        $this->assertCount(1, $summary->members);
        $this->assertSame($member->id()->toString(), $summary->members[0]->personId);
        $this->assertSame(300, $summary->members[0]->confirmedTicketBackAmount);
        $this->assertSame(0, $summary->members[0]->alreadySettledAmount);
        $this->assertSame(300, $summary->members[0]->outstandingAmount);
    }

    public function test_settling_a_member_zeroes_their_outstanding_amount(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();

        $this->settleMember->execute(new SettleProductionMemberCommand($production->id()->toString(), $member->id()->toString(), 1));

        $summary = $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 1));
        $this->assertSame(300, $summary->members[0]->alreadySettledAmount);
        $this->assertSame(0, $summary->members[0]->outstandingAmount);
    }

    public function test_settling_an_already_settled_member_is_rejected(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $this->settleMember->execute(new SettleProductionMemberCommand($production->id()->toString(), $member->id()->toString(), 1));

        $this->expectException(NothingToSettleException::class);
        $this->settleMember->execute(new SettleProductionMemberCommand($production->id()->toString(), $member->id()->toString(), 1));
    }

    public function test_settling_only_affects_the_targeted_member(): void
    {
        [$production, $memberOne] = $this->givenProductionWithOneMemberSale();

        $performance = $this->performances->findByProductionId($production->id())[0];
        $ticket = $this->tickets->findByProductionId($production->id())[0];
        $memberTwo = Person::create(3);
        $this->people->save($memberTwo);
        $secondReservation = Reservation::create($performance->id(), $ticket->id(), 'B', 'b@example.com', 1, 3000, null, $memberTwo->id());
        $secondReservation->checkIn(null);
        $this->reservations->save($secondReservation);

        $this->settleMember->execute(new SettleProductionMemberCommand($production->id()->toString(), $memberOne->id()->toString(), 1));

        $summary = $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 1));
        $lineTwo = current(array_filter($summary->members, static fn ($line) => $line->personId === $memberTwo->id()->toString()));
        $this->assertSame(300, $lineTwo->outstandingAmount);
    }

    public function test_cancelling_a_settlement_restores_the_outstanding_amount(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $this->settleMember->execute(new SettleProductionMemberCommand($production->id()->toString(), $member->id()->toString(), 1));

        $this->cancelSettlement->execute(new CancelProductionMemberSettlementCommand($production->id()->toString(), $member->id()->toString(), 1));

        $summary = $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 1));
        $this->assertSame(0, $summary->members[0]->alreadySettledAmount);
        $this->assertSame(300, $summary->members[0]->outstandingAmount);
    }

    public function test_cancelling_with_nothing_settled_is_rejected(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();

        $this->expectException(NothingToCancelException::class);
        $this->cancelSettlement->execute(new CancelProductionMemberSettlementCommand($production->id()->toString(), $member->id()->toString(), 1));
    }

    public function test_cancelling_twice_in_a_row_is_rejected(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $this->settleMember->execute(new SettleProductionMemberCommand($production->id()->toString(), $member->id()->toString(), 1));
        $this->cancelSettlement->execute(new CancelProductionMemberSettlementCommand($production->id()->toString(), $member->id()->toString(), 1));

        $this->expectException(NothingToCancelException::class);
        $this->cancelSettlement->execute(new CancelProductionMemberSettlementCommand($production->id()->toString(), $member->id()->toString(), 1));
    }

    public function test_non_primary_manager_cannot_cancel_a_settlement(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $this->settleMember->execute(new SettleProductionMemberCommand($production->id()->toString(), $member->id()->toString(), 1));

        $stranger = Person::create(99);
        $this->people->save($stranger);

        $this->expectException(SettlementAccessDeniedException::class);
        $this->cancelSettlement->execute(new CancelProductionMemberSettlementCommand($production->id()->toString(), $member->id()->toString(), 99));
    }

    public function test_non_primary_manager_cannot_view_the_settlement_summary(): void
    {
        [$production] = $this->givenProductionWithOneMemberSale();

        $stranger = Person::create(99);
        $this->people->save($stranger);

        $this->expectException(SettlementAccessDeniedException::class);
        $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 99));
    }

    /**
     * 担当者権限をメンバー管理へ統合・整理 instruction §会計担当仕様訂正: 会計担当
     * (ACCOUNTING_MANAGER) covers Settlement too, not just Budget/Expense/
     * JournalEntry - an ACCOUNTING_MANAGER delegate (not the
     * PrimaryManager) can view the summary, settle a member, and cancel
     * that settlement, exactly like a PrimaryManager can.
     */
    public function test_accounting_manager_delegate_can_view_settle_and_cancel_settlement(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();

        $accountingDelegate = Person::create(50);
        $this->people->save($accountingDelegate);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $accountingDelegate->id(),
            RoleKey::accountingManager(),
            $production->primaryManagerPersonId()
        ));

        $summary = $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 50));
        $this->assertSame(300, $summary->members[0]->outstandingAmount);

        $this->settleMember->execute(new SettleProductionMemberCommand($production->id()->toString(), $member->id()->toString(), 50));

        $afterSettle = $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 50));
        $this->assertSame(300, $afterSettle->members[0]->alreadySettledAmount);
        $this->assertSame(0, $afterSettle->members[0]->outstandingAmount);

        $this->cancelSettlement->execute(new CancelProductionMemberSettlementCommand($production->id()->toString(), $member->id()->toString(), 50));

        $afterCancel = $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 50));
        $this->assertSame(0, $afterCancel->members[0]->alreadySettledAmount);
        $this->assertSame(300, $afterCancel->members[0]->outstandingAmount);
    }

    /**
     * A Role with no Settlement.Manage in its Permission Set (e.g.
     * CHECKIN_MANAGER - reception staff) must still be denied Settlement
     * access, confirming the grant is specific to ACCOUNTING_MANAGER, not
     * accidentally opened up to every delegate Role.
     */
    public function test_a_delegate_without_accounting_manager_still_cannot_view_the_settlement_summary(): void
    {
        [$production] = $this->givenProductionWithOneMemberSale();

        $checkInDelegate = Person::create(51);
        $this->people->save($checkInDelegate);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $checkInDelegate->id(),
            RoleKey::checkInManager(),
            $production->primaryManagerPersonId()
        ));

        $this->expectException(SettlementAccessDeniedException::class);
        $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 51));
    }

    // --- PR #7 review: a failed Reservation read is not "no Reservations" ---

    public function test_settling_stops_when_the_reservations_cannot_be_read(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $settle = new SettleProductionMemberUseCase(
            $this->productions,
            $this->settlements,
            new ProductionSettlementCalculator($this->performances, new ReadFailingReservationRepository($this->reservations), $this->tickets),
            $this->identity,
            $this->authorization,
            new InMemoryTransactionManager()
        );

        try {
            $settle->execute(new SettleProductionMemberCommand($production->id()->toString(), $member->id()->toString(), 1));
            $this->fail('Settled although the Reservations could not be read.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Failed to read the Reservations', $exception->getMessage());
        }

        $this->assertNull($this->settlements->findByProductionAndPerson($production->id(), $member->id()));
    }

    public function test_the_summary_is_not_shown_as_nothing_owed_when_the_reservations_cannot_be_read(): void
    {
        [$production] = $this->givenProductionWithOneMemberSale();
        $summary = new GetProductionSettlementSummaryUseCase(
            $this->productions,
            $this->membershipContract,
            $this->people,
            $this->settlements,
            new ProductionSettlementCalculator($this->performances, new ReadFailingReservationRepository($this->reservations), $this->tickets),
            $this->identity,
            $this->authorization
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to read the Reservations');

        $summary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 1));
    }

    // --- PR #7 review (R4): concurrent settlements ---
    //
    // Settling and cancelling take the exclusive Production lock first in
    // their transaction and read everything they decide on under it. The
    // `beforeLockGranted` hook plays the request that held the lock first
    // and committed while this one waited.

    private function settle(Production $production, \StageArt\Domain\Person\Person $member): void
    {
        $this->settleMember->execute(new SettleProductionMemberCommand($production->id()->toString(), $member->id()->toString(), 1));
    }

    /** A Check-in of one more seat for the member, committed by another request. */
    private function anotherCheckedInSeat(Production $production, \StageArt\Domain\Person\Person $member): void
    {
        $performance = $this->performances->findByProductionId($production->id())[0];
        $ticket = $this->tickets->findByProductionId($production->id())[0];
        $reservation = Reservation::create($performance->id(), $ticket->id(), 'B', 'b@example.com', 1, 3000, null, $member->id());
        $reservation->checkIn(null);
        $this->reservations->save($reservation);
    }

    private function settlementOf(Production $production, \StageArt\Domain\Person\Person $member): ?\StageArt\Domain\Settlement\ProductionMemberSettlement
    {
        return $this->settlements->findByProductionAndPerson($production->id(), $member->id());
    }

    public function test_settling_reads_and_writes_under_the_exclusive_production_lock(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $transactions = new \StageArt\Tests\Support\RollbackingTransactionManager();
        $inTransactionAtLock = null;
        $this->productions->beforeLockGranted = static function () use ($transactions, &$inTransactionAtLock): void {
            $inTransactionAtLock = $transactions->isInTransaction();
        };
        $settle = new SettleProductionMemberUseCase(
            $this->productions,
            $this->settlements,
            new ProductionSettlementCalculator($this->performances, $this->reservations, $this->tickets),
            $this->identity,
            $this->authorization,
            $transactions
        );

        $settle->execute(new SettleProductionMemberCommand($production->id()->toString(), $member->id()->toString(), 1));

        $this->assertSame([['FOR UPDATE', $production->id()->toString()]], $this->productions->locks);
        $this->assertTrue($inTransactionAtLock);
        $this->assertSame(1, $transactions->commits());
        $this->assertSame(300, $this->settlementOf($production, $member)->totalSettledAmount());
    }

    public function test_a_second_concurrent_settlement_of_the_same_member_finds_nothing_left(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $this->productions->beforeLockGranted = function () use ($production, $member): void {
            $this->settle($production, $member); // the other request, committed first
        };

        try {
            $this->settle($production, $member);
            $this->fail('The same Ticket Back was settled twice.');
        } catch (NothingToSettleException $exception) {
            $this->addToAssertionCount(1);
        }

        $settlement = $this->settlementOf($production, $member);
        $this->assertSame(300, $settlement->totalSettledAmount(), 'settled once, not twice');
        $this->assertSame(300, $settlement->lastSettledAmount());
    }

    /**
     * A Check-in committed between this request's first look and its lock
     * is part of what it settles: the amount is read under the lock.
     */
    public function test_a_check_in_committed_before_the_lock_is_settled_too(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $this->productions->beforeLockGranted = function () use ($production, $member): void {
            $this->anotherCheckedInSeat($production, $member);
        };

        $this->settle($production, $member);

        // 2 seats * 3,000円 * 10%.
        $this->assertSame(600, $this->settlementOf($production, $member)->totalSettledAmount());
        $summary = $this->getSummary->execute(new GetProductionSettlementSummaryQuery($production->id()->toString(), 1));
        $this->assertSame(0, $summary->members[0]->outstandingAmount);
    }

    /**
     * With an existing settlement record: a cancellation committed while
     * this settlement waited is read, not overwritten with the record as
     * it was before.
     */
    public function test_settling_after_a_cancellation_committed_meanwhile_keeps_both(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $this->settle($production, $member);
        $this->anotherCheckedInSeat($production, $member);
        $this->productions->beforeLockGranted = function () use ($production, $member): void {
            $this->cancelSettlement->execute(new CancelProductionMemberSettlementCommand($production->id()->toString(), $member->id()->toString(), 1));
        };

        $this->settle($production, $member);

        $settlement = $this->settlementOf($production, $member);
        $this->assertSame(600, $settlement->totalSettledAmount(), 'the whole 600円 owed after the cancellation');
        $this->assertSame(600, $settlement->lastSettledAmount());
    }

    public function test_a_second_concurrent_cancellation_finds_nothing_to_cancel(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $this->settle($production, $member);
        $command = new CancelProductionMemberSettlementCommand($production->id()->toString(), $member->id()->toString(), 1);
        $this->productions->beforeLockGranted = function () use ($command): void {
            $this->cancelSettlement->execute($command);
        };

        try {
            $this->cancelSettlement->execute($command);
            $this->fail('The same settlement was cancelled twice.');
        } catch (NothingToCancelException $exception) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, $this->settlementOf($production, $member)->totalSettledAmount(), 'not -300');
    }

    public function test_a_failed_production_lock_settles_nothing_and_the_request_can_be_sent_again(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $this->productions->lockFailure = new \RuntimeException('Failed to lock the Production. Lock wait timeout exceeded; try restarting transaction');

        try {
            $this->settle($production, $member);
            $this->fail('Settled without the Production lock.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Lock wait timeout exceeded', $exception->getMessage());
        }

        $this->assertNull($this->settlementOf($production, $member));

        $this->productions->lockFailure = null;
        $this->settle($production, $member);
        $this->assertSame(300, $this->settlementOf($production, $member)->totalSettledAmount());
    }

    // --- PR #7 review (R5): the settlement of a COMPLETED Production is closed ---

    /**
     * @return array<string, array{0: string}>
     */
    public function settledStatuses(): array
    {
        return ['COMPLETED' => ['COMPLETED'], 'ARCHIVED' => ['ARCHIVED']];
    }

    /**
     * @dataProvider settledStatuses
     */
    public function test_a_settlement_is_not_cancelled_once_the_production_is_completed(string $status): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $this->settle($production, $member);
        \StageArt\Tests\Support\ProductionStatusFixture::force($production, $status);
        $this->productions->save($production);

        try {
            $this->cancelSettlement->execute(new CancelProductionMemberSettlementCommand($production->id()->toString(), $member->id()->toString(), 1));
            $this->fail("A settlement of a {$status} Production was cancelled.");
        } catch (\StageArt\Application\Production\ProductionClosedException $exception) {
            $this->assertSame($status, $exception->productionStatus());
        }

        $settlement = $this->settlementOf($production, $member);
        $this->assertSame(300, $settlement->totalSettledAmount(), 'the settlement history is kept');
        $this->assertSame(300, $settlement->lastSettledAmount());
    }

    /**
     * Production Completion committed while the cancellation waited for the
     * exclusive lock: the cancellation sees COMPLETED and changes nothing.
     */
    public function test_a_cancellation_waiting_for_production_completion_is_refused_after_it_commits(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        $this->settle($production, $member);
        $this->productions->beforeLockGranted = function () use ($production): void {
            $completed = clone $production;
            \StageArt\Tests\Support\ProductionStatusFixture::force($completed, 'COMPLETED');
            $this->productions->save($completed);
        };

        $this->expectException(\StageArt\Application\Production\ProductionClosedException::class);

        try {
            $this->cancelSettlement->execute(new CancelProductionMemberSettlementCommand($production->id()->toString(), $member->id()->toString(), 1));
        } finally {
            $this->assertSame(300, $this->settlementOf($production, $member)->totalSettledAmount());
            $this->assertSame('COMPLETED', $this->productions->findById($production->id())->status()->toString());
        }
    }

    public function test_settling_is_refused_once_completed(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        \StageArt\Tests\Support\ProductionStatusFixture::force($production, 'COMPLETED');
        $this->productions->save($production);

        $this->expectException(\StageArt\Application\Production\ProductionClosedException::class);

        try {
            $this->settle($production, $member);
        } finally {
            $this->assertNull($this->settlementOf($production, $member));
        }
    }

    /**
     * A CANCELLED Production's members can still be settled - and a
     * settlement corrected - for the Performances that did take place.
     */
    public function test_a_cancelled_production_can_still_be_settled_and_corrected(): void
    {
        [$production, $member] = $this->givenProductionWithOneMemberSale();
        \StageArt\Tests\Support\ProductionStatusFixture::force($production, 'CANCELLED');
        $this->productions->save($production);

        $this->settle($production, $member);
        $this->assertSame(300, $this->settlementOf($production, $member)->totalSettledAmount());

        $this->cancelSettlement->execute(new CancelProductionMemberSettlementCommand($production->id()->toString(), $member->id()->toString(), 1));
        $this->assertSame(0, $this->settlementOf($production, $member)->totalSettledAmount());
    }
}
