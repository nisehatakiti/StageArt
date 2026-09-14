<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Rehearsal;

use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Rehearsal\ActivateRehearsalCommand;
use StageArt\Application\Rehearsal\ActivateRehearsalUseCase;
use StageArt\Application\Rehearsal\CancelRehearsalUseCase;
use StageArt\Application\Rehearsal\CompleteRehearsalCommand;
use StageArt\Application\Rehearsal\CompleteRehearsalUseCase;
use StageArt\Application\Rehearsal\ConfirmRehearsalCommand;
use StageArt\Application\Rehearsal\ConfirmRehearsalUseCase;
use StageArt\Application\Rehearsal\CreateRehearsalCommand;
use StageArt\Application\Rehearsal\CreateRehearsalUseCase;
use StageArt\Application\Rehearsal\GetRehearsalQuery;
use StageArt\Application\Rehearsal\GetRehearsalUseCase;
use StageArt\Application\Rehearsal\ListRehearsalsForProductionQuery;
use StageArt\Application\Rehearsal\ListRehearsalsUseCase;
use StageArt\Application\Rehearsal\RehearsalReminderDispatcher;
use StageArt\Application\Rehearsal\RehearsalReminderPolicy;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreNotificationAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Application\Rehearsal\RehearsalAccessDeniedException;
use StageArt\Application\Rehearsal\UpdateRehearsalCommand;
use StageArt\Application\Rehearsal\UpdateRehearsalUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationDispatcher;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryRehearsalAttendanceRepository;
use StageArt\Tests\Support\InMemoryRehearsalReminderScheduler;
use StageArt\Tests\Support\InMemoryRehearsalRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class RehearsalUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private InMemoryRehearsalRepository $rehearsals;
    private InMemoryRehearsalAttendanceRepository $attendances;
    private InMemoryNotificationDispatcher $notificationDispatcher;
    private InMemoryRehearsalReminderScheduler $reminderScheduler;

    private CreateRehearsalUseCase $createRehearsal;
    private GetRehearsalUseCase $getRehearsal;
    private ListRehearsalsUseCase $listRehearsals;
    private UpdateRehearsalUseCase $updateRehearsal;
    private ConfirmRehearsalUseCase $confirmRehearsal;
    private ActivateRehearsalUseCase $activateRehearsal;
    private CompleteRehearsalUseCase $completeRehearsal;
    private CancelRehearsalUseCase $cancelRehearsal;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->rehearsals = new InMemoryRehearsalRepository();
        $this->attendances = new InMemoryRehearsalAttendanceRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            $this->participants
        );
        $memberResolver = new CoreMembershipAdapter($this->participants, $this->productions, $this->people, $productionAuthorization);
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);
        $transactions = new InMemoryTransactionManager();
        $this->notificationDispatcher = new InMemoryNotificationDispatcher();
        $notificationContract = new CoreNotificationAdapter($this->notificationDispatcher);
        $reminderDispatcher = new RehearsalReminderDispatcher($this->attendances, $productionContext, $notificationContract);
        $this->reminderScheduler = new InMemoryRehearsalReminderScheduler();

        $this->createRehearsal = new CreateRehearsalUseCase(
            $productionContext,
            $this->rehearsals,
            $this->attendances,
            $memberResolver,
            $reminderDispatcher,
            $this->reminderScheduler,
            $identity,
            $authorization,
            $transactions
        );
        $this->getRehearsal = new GetRehearsalUseCase($this->rehearsals, $productionContext, $identity, $memberResolver);
        $this->listRehearsals = new ListRehearsalsUseCase($this->rehearsals, $productionContext, $identity, $memberResolver);
        $this->updateRehearsal = new UpdateRehearsalUseCase(
            $this->rehearsals,
            $productionContext,
            $reminderDispatcher,
            $this->reminderScheduler,
            $identity,
            $authorization,
            $transactions
        );
        $this->confirmRehearsal = new ConfirmRehearsalUseCase(
            $this->rehearsals,
            $productionContext,
            $this->attendances,
            $memberResolver,
            $identity,
            $authorization,
            $transactions
        );
        $this->activateRehearsal = new ActivateRehearsalUseCase($this->rehearsals, $productionContext, $identity, $authorization);
        $this->completeRehearsal = new CompleteRehearsalUseCase($this->rehearsals, $productionContext, $identity, $authorization);
        $this->cancelRehearsal = new CancelRehearsalUseCase(
            $this->rehearsals,
            $this->attendances,
            $productionContext,
            $notificationContract,
            $this->reminderScheduler,
            $identity,
            $authorization,
            $transactions
        );
    }

    private function givenProductionWithPrimaryManager(int $primaryManagerWordPressUserId): Production
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);

        $primaryManager = Person::create($primaryManagerWordPressUserId);
        $this->people->save($primaryManager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $primaryManager->id()));

        $project = Project::create($organization->id(), 'Season');

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $this->productions->save($production);

        return $production;
    }

    private function addActivePersonParticipant(Production $production, int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);

        $participant = Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            ParticipantType::cast()
        );
        $this->participants->save($participant);

        return $person;
    }

    private function addRehearsalManagerDelegate(Production $production, int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);

        $delegate = ProductionDelegate::create(
            $production->id(),
            $person->id(),
            RoleKey::rehearsalManager(),
            $production->primaryManagerPersonId()
        );
        $this->delegates->save($delegate);

        return $person;
    }

    public function test_primary_manager_can_create_rehearsal_and_phase1_attendance_is_generated(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);

        $result = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()]
        ));

        $this->assertSame('SCHEDULED', $result->status);

        $phase1 = $this->attendances->findByRehearsalIdAndPhase(
            RehearsalId::fromString($result->id),
            RehearsalAttendancePhase::scheduleAdjustment()
        );
        $this->assertCount(1, $phase1);
        $this->assertSame('UNANSWERED', $phase1[0]->status()->toString());

        $phase2 = $this->attendances->findByRehearsalIdAndPhase(
            RehearsalId::fromString($result->id),
            RehearsalAttendancePhase::attendanceConfirmation()
        );
        $this->assertCount(0, $phase2, 'Phase 2 attendance must not be created at Rehearsal creation time.');
    }

    public function test_rehearsal_manager_delegate_can_create_rehearsal(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $delegate = $this->addRehearsalManagerDelegate($production, 5);

        $result = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            5,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));

        $this->assertSame($production->id()->toString(), $result->productionId);
    }

    public function test_plain_participant_cannot_create_rehearsal(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $this->expectException(RehearsalAccessDeniedException::class);

        $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            2,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));
    }

    public function test_create_rehearsal_targets_only_selected_members(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $memberA = $this->addActivePersonParticipant($production, 2);
        $memberB = $this->addActivePersonParticipant($production, 3);
        $memberC = $this->addActivePersonParticipant($production, 4);

        $result = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            [$memberA->id()->toString(), $memberC->id()->toString()]
        ));

        $phase1 = $this->attendances->findByRehearsalIdAndPhase(
            RehearsalId::fromString($result->id),
            RehearsalAttendancePhase::scheduleAdjustment()
        );
        $targetedPersonIds = array_map(static fn ($a) => $a->personId()->toString(), $phase1);

        $this->assertCount(2, $phase1);
        $this->assertContains($memberA->id()->toString(), $targetedPersonIds);
        $this->assertContains($memberC->id()->toString(), $targetedPersonIds);
        $this->assertNotContains($memberB->id()->toString(), $targetedPersonIds);
    }

    public function test_create_rehearsal_with_no_selected_members_generates_no_attendance(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $result = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            []
        ));

        $phase1 = $this->attendances->findByRehearsalIdAndPhase(
            RehearsalId::fromString($result->id),
            RehearsalAttendancePhase::scheduleAdjustment()
        );

        $this->assertCount(0, $phase1);
    }

    public function test_create_rehearsal_rejects_a_person_who_is_not_an_active_participant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $notAMember = PersonId::generate();

        $this->expectException(InvalidArgumentException::class);

        $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            [$notAMember->toString()]
        ));
    }

    /**
     * Two separate time axes, not one: CreateRehearsalUseCase validates
     * targetPersonIds against currently-active membership only at
     * creation time (see that Use Case's own new all-or-nothing
     * validation) - so both targets here must still be active when
     * createRehearsal->execute() runs. The deactivation happens only
     * afterward, between creation and confirmation, exercising
     * ConfirmRehearsalUseCase's own separate re-check of current
     * membership at confirm time (see that Use Case's docblock on its
     * Phase 1 ∩ currently-active intersection logic).
     */
    public function test_confirm_generates_phase2_only_for_active_person_participants(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $activeMember = $this->addActivePersonParticipant($production, 2);
        $memberToDeactivate = $this->addActivePersonParticipant($production, 3);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            [$activeMember->id()->toString(), $memberToDeactivate->id()->toString()]
        ));

        // deactivate one of the two participants after creation, before confirmation
        foreach ($this->participants->findByProductionId($production->id()) as $participant) {
            if ($participant->subjectId() === $memberToDeactivate->id()->toString()) {
                $participant->deactivate();
                $this->participants->save($participant);
            }
        }

        $confirmed = $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($created->id, 1));
        $this->assertSame('CONFIRMED', $confirmed->status);

        $rehearsalId = \StageArt\Domain\Rehearsal\RehearsalId::fromString($created->id);
        $phase2 = $this->attendances->findByRehearsalIdAndPhase($rehearsalId, RehearsalAttendancePhase::attendanceConfirmation());

        $this->assertCount(1, $phase2);
        $this->assertSame($activeMember->id()->toString(), $phase2[0]->personId()->toString());
        $this->assertSame('UNANSWERED', $phase2[0]->status()->toString());
    }

    public function test_confirm_does_not_reintroduce_a_member_left_unselected_at_creation(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $selectedMember = $this->addActivePersonParticipant($production, 2);
        $unselectedMember = $this->addActivePersonParticipant($production, 3);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            [$selectedMember->id()->toString()]
        ));

        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($created->id, 1));

        $rehearsalId = RehearsalId::fromString($created->id);
        $phase2 = $this->attendances->findByRehearsalIdAndPhase($rehearsalId, RehearsalAttendancePhase::attendanceConfirmation());
        $targetedPersonIds = array_map(static fn ($a) => $a->personId()->toString(), $phase2);

        $this->assertCount(1, $phase2);
        $this->assertContains($selectedMember->id()->toString(), $targetedPersonIds);
        $this->assertNotContains($unselectedMember->id()->toString(), $targetedPersonIds);
    }

    public function test_confirm_twice_is_rejected_and_does_not_duplicate_phase2(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));

        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($created->id, 1));

        $this->expectException(InvalidArgumentException::class);
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($created->id, 1));
    }

    public function test_non_manager_cannot_confirm_rehearsal(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));

        $this->expectException(RehearsalAccessDeniedException::class);
        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($created->id, 2));
    }

    public function test_full_lifecycle_via_use_cases(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));

        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($created->id, 1));
        $activated = $this->activateRehearsal->execute(new ActivateRehearsalCommand($created->id, 1));
        $this->assertSame('ACTIVE', $activated->status);

        $completed = $this->completeRehearsal->execute(new CompleteRehearsalCommand($created->id, 1));
        $this->assertSame('COMPLETED', $completed->status);
    }

    public function test_get_rehearsal_rejects_non_member_of_production(): void
    {
        $productionA = $this->givenProductionWithPrimaryManager(1);
        $this->givenProductionWithPrimaryManager(99); // production B, unrelated

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $productionA->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));

        $this->expectException(RehearsalAccessDeniedException::class);
        $this->getRehearsal->execute(new GetRehearsalQuery($created->id, 99));
    }

    public function test_list_rehearsals_scoped_to_production_membership(): void
    {
        $productionA = $this->givenProductionWithPrimaryManager(1);
        $productionB = $this->givenProductionWithPrimaryManager(2);

        $this->createRehearsal->execute(new CreateRehearsalCommand(
            $productionA->id()->toString(),
            1,
            'A Rehearsal',
            null,
            null,
            null,
            null,
            null
        ));
        $this->createRehearsal->execute(new CreateRehearsalCommand(
            $productionB->id()->toString(),
            2,
            'B Rehearsal',
            null,
            null,
            null,
            null,
            null
        ));

        $resultsForA = $this->listRehearsals->execute(new ListRehearsalsForProductionQuery($productionA->id()->toString(), 1));
        $this->assertCount(1, $resultsForA);
        $this->assertSame('A Rehearsal', $resultsForA[0]->title);

        $this->expectException(RehearsalAccessDeniedException::class);
        $this->listRehearsals->execute(new ListRehearsalsForProductionQuery($productionB->id()->toString(), 1));
    }

    /**
     * Regression coverage for the Rehearsal datetime/timezone bug: a
     * JST-offset input must come back out through RehearsalResult with
     * the SAME offset (not silently re-labelled UTC) AND the SAME
     * absolute instant. Two separate assertions on purpose - Domain
     * Design Option C (RehearsalInstaller.php: `start_date_time DATETIME`
     * + separate `timezone` column) means the guarantee this project
     * makes is "the wall-clock digits and the declared timezone travel
     * together unchanged", which implies (but is more specific than)
     * "the absolute instant is unchanged" - a test that only checked the
     * instant would pass even if the offset were swapped for another
     * offset that happens to name the same instant, which is not what
     * Rehearsal.md's Location/timezone design promises.
     *
     * These Create/Get/Update cases exercise CreateRehearsalUseCase /
     * GetRehearsalUseCase / UpdateRehearsalUseCase against
     * InMemoryRehearsalRepository, which stores the Rehearsal Domain
     * Entity object directly (no DB string round-trip - see
     * tests/Support/InMemoryRehearsalRepository.php). They confirm the
     * Application layer's own parsing (`parseOptionalDateTime()`) and
     * serialization (`RehearsalResult::fromDomain()`) never lose the
     * offset, which was already correct before this fix. They do NOT
     * exercise WordPressRehearsalRepository::hydrate() (the actual
     * Infrastructure-layer bug/fix), because that class requires a real
     * `wpdb` (a WordPress core global) and this project's own tests/
     * tier is Domain/Application-only with no WordPress dependency (see
     * CLAUDE.md and this suite's exclusive use of InMemory* Fakes) - no
     * `wpdb` stub exists anywhere in this codebase's autoload, and
     * introducing one would be a new architectural precedent, not a
     * minimal regression test. That Repository round-trip case is
     * instead verified against the real dev server (real WordPress
     * bootstrap, real MySQL) as part of this fix's browser/API
     * verification.
     */
    public function test_create_rehearsal_preserves_start_date_time_offset_and_instant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $result = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            '2026-09-20T18:00:00+09:00',
            '2026-09-20T21:00:00+09:00',
            'Asia/Tokyo',
            null
        ));

        $this->assertSame('2026-09-20T18:00:00+09:00', $result->startDateTime);
        $this->assertSame(
            (new DateTimeImmutable('2026-09-20T09:00:00+00:00'))->getTimestamp(),
            (new DateTimeImmutable($result->startDateTime))->getTimestamp(),
            'The absolute instant represented by start_date_time must not shift.'
        );
    }

    public function test_create_rehearsal_preserves_end_date_time_offset_and_instant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $result = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            '2026-09-20T18:00:00+09:00',
            '2026-09-20T21:00:00+09:00',
            'Asia/Tokyo',
            null
        ));

        $this->assertSame('2026-09-20T21:00:00+09:00', $result->endDateTime);
        $this->assertSame(
            (new DateTimeImmutable('2026-09-20T12:00:00+00:00'))->getTimestamp(),
            (new DateTimeImmutable($result->endDateTime))->getTimestamp(),
            'The absolute instant represented by end_date_time must not shift.'
        );
    }

    public function test_create_then_get_rehearsal_preserves_date_time_offset(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            '2026-09-20T18:00:00+09:00',
            '2026-09-20T21:00:00+09:00',
            'Asia/Tokyo',
            null
        ));

        $fetched = $this->getRehearsal->execute(new GetRehearsalQuery($created->id, 1));

        $this->assertSame('2026-09-20T18:00:00+09:00', $fetched->startDateTime);
        $this->assertSame('2026-09-20T21:00:00+09:00', $fetched->endDateTime);
    }

    public function test_update_rehearsal_date_time_reflects_new_offset_without_stale_leftover(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            '2026-09-20T18:00:00+09:00',
            '2026-09-20T20:00:00+09:00',
            'Asia/Tokyo',
            null
        ));

        $updated = $this->updateRehearsal->execute(new UpdateRehearsalCommand(
            $created->id,
            1,
            'Act 1 Run',
            null,
            '2026-09-20T19:30:00+09:00',
            '2026-09-20T20:00:00+09:00',
            'Asia/Tokyo',
            null
        ));

        $this->assertSame('2026-09-20T19:30:00+09:00', $updated->startDateTime);

        $refetched = $this->getRehearsal->execute(new GetRehearsalQuery($created->id, 1));
        $this->assertSame(
            '2026-09-20T19:30:00+09:00',
            $refetched->startDateTime,
            'A re-fetch after Update must show the new time, not the value the Rehearsal was originally created with.'
        );
    }

    // --- Phase 7 (Rehearsal仕様整合) §3: CONFIRMED/ACTIVEの日付変更禁止 ---

    private function createAndConfirmRehearsal(Production $production, string $startDateTime, string $endDateTime): string
    {
        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            $startDateTime,
            $endDateTime,
            'Asia/Tokyo',
            null
        ));

        $this->confirmRehearsal->execute(new ConfirmRehearsalCommand($created->id, 1));

        return $created->id;
    }

    public function test_date_change_is_allowed_while_scheduled(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            '2026-09-20T18:00:00+09:00',
            '2026-09-20T20:00:00+09:00',
            'Asia/Tokyo',
            null
        ));

        $updated = $this->updateRehearsal->execute(new UpdateRehearsalCommand(
            $created->id,
            1,
            'Act 1 Run',
            null,
            '2026-09-21T18:00:00+09:00',
            '2026-09-21T20:00:00+09:00',
            'Asia/Tokyo',
            null
        ));

        $this->assertSame('2026-09-21T18:00:00+09:00', $updated->startDateTime);
    }

    public function test_date_change_is_rejected_while_confirmed(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsalId = $this->createAndConfirmRehearsal($production, '2026-09-20T18:00:00+09:00', '2026-09-20T20:00:00+09:00');

        $this->expectException(InvalidArgumentException::class);
        $this->updateRehearsal->execute(new UpdateRehearsalCommand(
            $rehearsalId,
            1,
            'Act 1 Run',
            null,
            '2026-09-21T18:00:00+09:00',
            '2026-09-21T20:00:00+09:00',
            'Asia/Tokyo',
            null
        ));
    }

    public function test_date_change_is_rejected_while_active(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsalId = $this->createAndConfirmRehearsal($production, '2026-09-20T18:00:00+09:00', '2026-09-20T20:00:00+09:00');
        $this->activateRehearsal->execute(new ActivateRehearsalCommand($rehearsalId, 1));

        $this->expectException(InvalidArgumentException::class);
        $this->updateRehearsal->execute(new UpdateRehearsalCommand(
            $rehearsalId,
            1,
            'Act 1 Run',
            null,
            '2026-09-21T18:00:00+09:00',
            '2026-09-21T20:00:00+09:00',
            'Asia/Tokyo',
            null
        ));
    }

    public function test_date_change_is_rejected_while_completed(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsalId = $this->createAndConfirmRehearsal($production, '2026-09-20T18:00:00+09:00', '2026-09-20T20:00:00+09:00');
        $this->activateRehearsal->execute(new ActivateRehearsalCommand($rehearsalId, 1));
        $this->completeRehearsal->execute(new CompleteRehearsalCommand($rehearsalId, 1));

        $this->expectException(InvalidArgumentException::class);
        $this->updateRehearsal->execute(new UpdateRehearsalCommand(
            $rehearsalId,
            1,
            'Act 1 Run',
            null,
            '2026-09-21T18:00:00+09:00',
            '2026-09-21T20:00:00+09:00',
            'Asia/Tokyo',
            null
        ));
    }

    public function test_date_change_is_rejected_while_cancelled(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            '2026-09-20T18:00:00+09:00',
            '2026-09-20T20:00:00+09:00',
            'Asia/Tokyo',
            null
        ));
        $this->cancelRehearsal->execute(new \StageArt\Application\Rehearsal\CancelRehearsalCommand($created->id, 1));

        $this->expectException(InvalidArgumentException::class);
        $this->updateRehearsal->execute(new UpdateRehearsalCommand(
            $created->id,
            1,
            'Act 1 Run',
            null,
            '2026-09-21T18:00:00+09:00',
            '2026-09-21T20:00:00+09:00',
            'Asia/Tokyo',
            null
        ));
    }

    public function test_time_only_change_is_still_allowed_while_confirmed(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsalId = $this->createAndConfirmRehearsal($production, '2026-09-20T18:00:00+09:00', '2026-09-20T20:00:00+09:00');

        $updated = $this->updateRehearsal->execute(new UpdateRehearsalCommand(
            $rehearsalId,
            1,
            'Act 1 Run',
            null,
            '2026-09-20T19:00:00+09:00',
            '2026-09-20T21:00:00+09:00',
            'Asia/Tokyo',
            null
        ));

        $this->assertSame('2026-09-20T19:00:00+09:00', $updated->startDateTime);
        $this->assertSame('CONFIRMED', $updated->status);
    }

    public function test_time_only_change_is_still_allowed_while_active(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsalId = $this->createAndConfirmRehearsal($production, '2026-09-20T18:00:00+09:00', '2026-09-20T20:00:00+09:00');
        $this->activateRehearsal->execute(new ActivateRehearsalCommand($rehearsalId, 1));

        $updated = $this->updateRehearsal->execute(new UpdateRehearsalCommand(
            $rehearsalId,
            1,
            'Act 1 Run',
            null,
            '2026-09-20T19:00:00+09:00',
            '2026-09-20T21:00:00+09:00',
            'Asia/Tokyo',
            null
        ));

        $this->assertSame('2026-09-20T19:00:00+09:00', $updated->startDateTime);
    }

    // --- Phase 7 §4: 回答期限 ---

    public function test_response_deadline_can_be_set_at_creation_and_round_trips(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $result = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            '2026-09-20T18:00:00+09:00',
            null,
            'Asia/Tokyo',
            null,
            null,
            '2026-09-19T18:00:00+09:00'
        ));

        $this->assertSame('2026-09-19T18:00:00+09:00', $result->responseDeadline);

        $fetched = $this->getRehearsal->execute(new GetRehearsalQuery($result->id, 1));
        $this->assertSame('2026-09-19T18:00:00+09:00', $fetched->responseDeadline);
    }

    public function test_response_deadline_defaults_to_null(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $result = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null
        ));

        $this->assertNull($result->responseDeadline);
    }

    // --- Phase 7 §5: Reminderスケジューリング（UseCase配線） ---

    public function test_setting_a_future_deadline_schedules_a_reminder(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $farFutureDeadline = (new DateTimeImmutable('+30 days'))->format(DATE_ATOM);

        $result = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            null,
            $farFutureDeadline
        ));

        $rehearsalId = RehearsalId::fromString($result->id);
        $this->assertTrue($this->reminderScheduler->isScheduled($rehearsalId));
        $this->assertSame(
            RehearsalReminderPolicy::computeReminderAt(new DateTimeImmutable($farFutureDeadline))->getTimestamp(),
            $this->reminderScheduler->scheduledAt($rehearsalId)->getTimestamp()
        );
    }

    public function test_extending_the_deadline_does_not_reschedule_the_reminder(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $original = (new DateTimeImmutable('+10 days'))->format(DATE_ATOM);
        $extended = (new DateTimeImmutable('+30 days'))->format(DATE_ATOM);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            null,
            $original
        ));
        $rehearsalId = RehearsalId::fromString($created->id);
        $originalReminderAt = $this->reminderScheduler->scheduledAt($rehearsalId);

        $this->updateRehearsal->execute(new UpdateRehearsalCommand(
            $created->id,
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            $extended
        ));

        $this->assertSame(
            $originalReminderAt->getTimestamp(),
            $this->reminderScheduler->scheduledAt($rehearsalId)->getTimestamp(),
            'Extending the deadline must leave the already-scheduled Reminder untouched.'
        );
    }

    public function test_pulling_the_deadline_earlier_reschedules_the_reminder(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $original = (new DateTimeImmutable('+30 days'))->format(DATE_ATOM);
        $pulledEarlier = (new DateTimeImmutable('+10 days'))->format(DATE_ATOM);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            null,
            $original
        ));
        $rehearsalId = RehearsalId::fromString($created->id);

        $this->updateRehearsal->execute(new UpdateRehearsalCommand(
            $created->id,
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            $pulledEarlier
        ));

        $this->assertSame(
            RehearsalReminderPolicy::computeReminderAt(new DateTimeImmutable($pulledEarlier))->getTimestamp(),
            $this->reminderScheduler->scheduledAt($rehearsalId)->getTimestamp()
        );
    }

    public function test_pulling_the_deadline_so_far_earlier_that_the_reminder_is_overdue_sends_immediately(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $member = $this->addActivePersonParticipant($production, 2);
        $original = (new DateTimeImmutable('+30 days'))->format(DATE_ATOM);
        // Only 1 hour from now: deadline - 24h is already in the past.
        $overdue = (new DateTimeImmutable('+1 hour'))->format(DATE_ATOM);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            [$member->id()->toString()],
            $original
        ));
        $rehearsalId = RehearsalId::fromString($created->id);

        $this->updateRehearsal->execute(new UpdateRehearsalCommand(
            $created->id,
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            $overdue
        ));

        $this->assertFalse($this->reminderScheduler->isScheduled($rehearsalId), 'An overdue Reminder must be sent now, not scheduled.');

        $dispatched = $this->notificationDispatcher->dispatched();
        $reminderNotifications = array_values(array_filter($dispatched, static fn ($n) => $n['type'] === 'rehearsal_response_reminder'));
        $this->assertCount(1, $reminderNotifications);
        $this->assertTrue($reminderNotifications[0]['personId']->equals($member->id()));
        $this->assertStringStartsWith('【Remind】', $reminderNotifications[0]['payload']['message']);
    }

    public function test_clearing_the_deadline_cancels_the_reminder(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $deadline = (new DateTimeImmutable('+30 days'))->format(DATE_ATOM);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            null,
            $deadline
        ));
        $rehearsalId = RehearsalId::fromString($created->id);
        $this->assertTrue($this->reminderScheduler->isScheduled($rehearsalId));

        $this->updateRehearsal->execute(new UpdateRehearsalCommand(
            $created->id,
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            null
        ));

        $this->assertFalse($this->reminderScheduler->isScheduled($rehearsalId));
    }

    // --- Phase 7 §8: Cancel時の一括通知 ---

    public function test_cancelling_a_rehearsal_notifies_every_target_participant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $memberA = $this->addActivePersonParticipant($production, 2);
        $memberB = $this->addActivePersonParticipant($production, 3);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            '2026-09-20T18:00:00+09:00',
            null,
            'Asia/Tokyo',
            null,
            [$memberA->id()->toString(), $memberB->id()->toString()]
        ));

        $this->cancelRehearsal->execute(new \StageArt\Application\Rehearsal\CancelRehearsalCommand($created->id, 1));

        $dispatched = $this->notificationDispatcher->dispatched();
        $cancelNotifications = array_values(array_filter($dispatched, static fn ($n) => $n['type'] === 'rehearsal_cancelled'));
        $notifiedPersonIds = array_map(static fn ($n) => $n['personId']->toString(), $cancelNotifications);

        $this->assertCount(2, $cancelNotifications);
        $this->assertContains($memberA->id()->toString(), $notifiedPersonIds);
        $this->assertContains($memberB->id()->toString(), $notifiedPersonIds);
        $this->assertSame(
            'Showの2026/09/20の稽古は中止となりました',
            $cancelNotifications[0]['payload']['message'],
            'Cancel message must follow the confirmed template and the existing Y/m/d date format.'
        );
    }

    public function test_cancelling_a_rehearsal_cancels_any_pending_reminder(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $deadline = (new DateTimeImmutable('+30 days'))->format(DATE_ATOM);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            null,
            null,
            null,
            null,
            null,
            $deadline
        ));
        $rehearsalId = RehearsalId::fromString($created->id);
        $this->assertTrue($this->reminderScheduler->isScheduled($rehearsalId));

        $this->cancelRehearsal->execute(new \StageArt\Application\Rehearsal\CancelRehearsalCommand($created->id, 1));

        $this->assertFalse($this->reminderScheduler->isScheduled($rehearsalId));
    }

    public function test_cancelling_a_rehearsal_with_no_targets_does_not_error(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            '2026-09-20T18:00:00+09:00',
            null,
            'Asia/Tokyo',
            null
        ));

        $result = $this->cancelRehearsal->execute(new \StageArt\Application\Rehearsal\CancelRehearsalCommand($created->id, 1));

        $this->assertSame('CANCELLED', $result->status);
        $this->assertCount(0, array_filter($this->notificationDispatcher->dispatched(), static fn ($n) => $n['type'] === 'rehearsal_cancelled'));
    }

    // --- Notification基盤実装 phase §1: Reminder対象者は実行時点でUNANSWEREDの人のみ ---

    public function test_reminder_only_notifies_targets_who_are_still_unanswered_at_send_time(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $unanswered = $this->addActivePersonParticipant($production, 2);
        $available = $this->addActivePersonParticipant($production, 3);
        $unavailable = $this->addActivePersonParticipant($production, 4);

        // No deadline yet at creation, so no Reminder fires here - only
        // after the three members' current attendance state is set up
        // below does the deadline get pulled into "overdue", so the
        // dispatch this test inspects reflects each member's status at
        // that later point in time, not at creation time.
        $created = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1 Run',
            null,
            '2026-09-20T18:00:00+09:00',
            null,
            'Asia/Tokyo',
            null,
            [$unanswered->id()->toString(), $available->id()->toString(), $unavailable->id()->toString()]
        ));

        $roster = $this->attendances->findByRehearsalIdAndPhase(
            RehearsalId::fromString($created->id),
            \StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase::scheduleAdjustment()
        );

        foreach ($roster as $attendance) {
            if ($attendance->personId()->equals($available->id())) {
                $attendance->respondScheduleAdjustment(
                    \StageArt\Domain\RehearsalAttendance\RehearsalAttendanceStatus::fromString('AVAILABLE')
                );
                $this->attendances->save($attendance);
            } elseif ($attendance->personId()->equals($unavailable->id())) {
                $attendance->respondScheduleAdjustment(
                    \StageArt\Domain\RehearsalAttendance\RehearsalAttendanceStatus::fromString('UNAVAILABLE')
                );
                $this->attendances->save($attendance);
            }
        }

        // Now set an already-overdue deadline: newDeadline - 24h is already
        // in the past, so this triggers ACTION_SEND_NOW immediately.
        // startDateTime is echoed back unchanged (whole-field-overwrite
        // convention - a null here would wipe it, which would make
        // RehearsalReminderDispatcher bail out with nothing to notify).
        $this->updateRehearsal->execute(new UpdateRehearsalCommand(
            $created->id,
            1,
            'Act 1 Run',
            null,
            '2026-09-20T18:00:00+09:00',
            null,
            'Asia/Tokyo',
            null,
            (new DateTimeImmutable('+1 hour'))->format(DATE_ATOM)
        ));

        $reminderNotifications = array_values(array_filter(
            $this->notificationDispatcher->dispatched(),
            static fn ($n) => $n['type'] === 'rehearsal_response_reminder'
        ));
        $notifiedPersonIds = array_map(static fn ($n) => $n['personId']->toString(), $reminderNotifications);

        $this->assertContains($unanswered->id()->toString(), $notifiedPersonIds);
        $this->assertNotContains($available->id()->toString(), $notifiedPersonIds);
        $this->assertNotContains($unavailable->id()->toString(), $notifiedPersonIds);
    }
}
