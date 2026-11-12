<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Rehearsal;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Rehearsal\CreateRehearsalCommand;
use StageArt\Application\Rehearsal\CreateRehearsalUseCase;
use StageArt\Application\Rehearsal\RehearsalAccessDeniedException;
use StageArt\Application\Rehearsal\RehearsalCreateRequestHash;
use StageArt\Application\Rehearsal\RehearsalCreateRequestRecord;
use StageArt\Application\Rehearsal\RehearsalIdempotencyConflictException;
use StageArt\Application\Rehearsal\RehearsalLifecycleNotifier;
use StageArt\Application\Rehearsal\RehearsalReminderDispatcher;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreNotificationAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Role\RoleKey;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationDispatcher;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryRehearsalAttendanceRepository;
use StageArt\Tests\Support\InMemoryRehearsalCreateRequestStore;
use StageArt\Tests\Support\InMemoryRehearsalReminderScheduler;
use StageArt\Tests\Support\InMemoryRehearsalRepository;
use StageArt\Tests\Support\RecordingNotificationContract;
use StageArt\Tests\Support\RollbackingTransactionManager;

/**
 * PR #7 R12 (docs/20-RehearsalManagementScreen.md §26): idempotency keys on
 * Rehearsal creation.
 */
final class CreateRehearsalIdempotencyTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private InMemoryRehearsalRepository $rehearsals;
    private InMemoryRehearsalAttendanceRepository $attendances;
    private InMemoryRehearsalCreateRequestStore $createRequests;
    private InMemoryRehearsalReminderScheduler $reminderScheduler;
    private RecordingNotificationContract $notifications;
    private RollbackingTransactionManager $transactions;
    private CreateRehearsalUseCase $createRehearsal;

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
        $this->createRequests = new InMemoryRehearsalCreateRequestStore();
        $this->reminderScheduler = new InMemoryRehearsalReminderScheduler();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService($organizationAuthorization, $this->delegates, $this->participants);
        $membership = new CoreMembershipAdapter($this->participants, $this->productions, $this->people, $productionAuthorization);
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));
        $this->transactions = new RollbackingTransactionManager($this->createRequests);
        $this->notifications = new RecordingNotificationContract($this->transactions);

        $this->createRehearsal = new CreateRehearsalUseCase(
            $productionContext,
            $this->rehearsals,
            $this->attendances,
            $membership,
            new RehearsalReminderDispatcher($this->attendances, $productionContext, new CoreNotificationAdapter(new InMemoryNotificationDispatcher())),
            $this->reminderScheduler,
            new CoreIdentityAdapter($this->people),
            new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people),
            $this->transactions,
            new RehearsalLifecycleNotifier($this->notifications),
            $this->createRequests
        );
    }

    private function givenProduction(int $managerWordPressUserId): Production
    {
        $organization = Organization::create(new OrganizationName('Theatre Co'));
        $this->organizations->save($organization);
        $manager = Person::create($managerWordPressUserId);
        $this->people->save($manager);
        $this->memberships->save(Membership::createOwnerMembership($organization->id(), $manager->id()));
        $project = Project::create($organization->id(), 'Season');
        $production = Production::create($project->id(), new ProductionName('Show'), $manager->id());
        $this->productions->save($production);

        return $production;
    }

    private function addMember(Production $production, int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->participants->save(Participant::create($production->id(), ParticipantSubjectType::person(), $person->id()->toString(), ParticipantType::cast()));

        return $person;
    }

    private function addRehearsalManager(Production $production, int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);
        $this->delegates->save(ProductionDelegate::create($production->id(), $person->id(), RoleKey::rehearsalManager(), $production->primaryManagerPersonId()));

        return $person;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function command(Production $production, ?string $key, array $overrides = []): CreateRehearsalCommand
    {
        $v = $overrides + [
            'user' => 1,
            'title' => '通し稽古',
            'description' => null,
            'start' => '2099-08-20T18:00:00+09:00',
            'end' => '2099-08-20T21:00:00+09:00',
            'timezone' => 'Asia/Tokyo',
            'location' => 'Studio A',
            'persons' => [],
            'deadline' => '2099-08-18T12:00:00+09:00',
            'activity' => null,
            'confirm' => false,
        ];

        return new CreateRehearsalCommand(
            $production->id()->toString(),
            $v['user'],
            $v['title'],
            $v['description'],
            $v['start'],
            $v['end'],
            $v['timezone'],
            $v['location'],
            $v['persons'],
            $v['deadline'],
            $v['activity'],
            $v['confirm'],
            $key
        );
    }

    private function rehearsalCount(Production $production): int
    {
        return count($this->rehearsals->findByProductionId($production->id()));
    }

    public function test_first_request_creates_and_notifies_once(): void
    {
        $production = $this->givenProduction(1);
        $a = $this->addMember($production, 2);
        $b = $this->addMember($production, 3);

        $outcome = $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1', ['persons' => [$a->id()->toString(), $b->id()->toString()]]));

        $this->assertFalse($outcome->replayed);
        $this->assertSame(1, $this->rehearsalCount($production));
        $this->assertSame(1, $this->createRequests->count());
        $this->assertCount(2, $this->notifications->sent());
        $this->assertSame(0, $this->notifications->sentInsideTransaction());
    }

    public function test_the_same_request_again_returns_the_same_rehearsal_without_creating_or_notifying(): void
    {
        $production = $this->givenProduction(1);
        $a = $this->addMember($production, 2);
        $command = $this->command($production, 'key-1', ['persons' => [$a->id()->toString()]]);

        $first = $this->createRehearsal->executeWithOutcome($command);
        $second = $this->createRehearsal->executeWithOutcome($command);
        $third = $this->createRehearsal->executeWithOutcome($command);

        $this->assertTrue($second->replayed);
        $this->assertTrue($third->replayed);
        $this->assertSame($first->result->toArray(), $second->result->toArray());
        $this->assertSame($first->result->id, $third->result->id);
        $this->assertSame(1, $this->rehearsalCount($production));
        $this->assertCount(1, $this->notifications->sent(), 'The planned notification is sent once.');
    }

    public function test_a_confirmed_on_create_resend_does_not_send_the_confirmed_notification_again(): void
    {
        $production = $this->givenProduction(1);
        $a = $this->addMember($production, 2);
        $command = $this->command($production, 'key-1', ['persons' => [$a->id()->toString()], 'confirm' => true]);

        $this->createRehearsal->executeWithOutcome($command);
        $this->createRehearsal->executeWithOutcome($command);

        $this->assertSame(['rehearsal_confirmed'], array_column($this->notifications->sent(), 'type'));
    }

    public function test_the_same_content_written_differently_is_the_same_request(): void
    {
        $production = $this->givenProduction(1);
        $a = $this->addMember($production, 2);
        $b = $this->addMember($production, 3);

        $first = $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1', [
            'persons' => [$a->id()->toString(), $b->id()->toString()],
        ]));
        $again = $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1', [
            'title' => '  通し稽古 ',
            'start' => '2099-08-20T09:00:00Z',
            'end' => '2099-08-20T21:00:00',
            'deadline' => '2099-08-18T03:00:00Z',
            'location' => 'Studio A ',
            'description' => '',
            'persons' => [strtoupper($b->id()->toString()), $a->id()->toString(), $b->id()->toString()],
            'activity' => 'REHEARSAL',
        ]));

        $this->assertTrue($again->replayed);
        $this->assertSame($first->result->id, $again->result->id);
        $this->assertSame(1, $this->rehearsalCount($production));
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public function differentContent(): array
    {
        return [
            'title' => [['title' => '別の稽古']],
            'start' => [['start' => '2099-08-20T18:30:00+09:00']],
            'end' => [['end' => '2099-08-20T22:00:00+09:00']],
            'deadline' => [['deadline' => null]],
            'location' => [['location' => 'Studio B']],
            'description' => [['description' => 'メモ']],
            'timezone' => [['timezone' => 'Asia/Seoul']],
            'activity' => [['activity' => 'MEETING']],
            'confirm' => [['confirm' => true]],
        ];
    }

    /**
     * @dataProvider differentContent
     * @param array<string, mixed> $change
     */
    public function test_the_same_key_with_different_content_is_a_conflict(array $change): void
    {
        $production = $this->givenProduction(1);
        $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1'));
        $notified = count($this->notifications->sent());

        try {
            $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1', $change));
            $this->fail('A different request with the same key was accepted.');
        } catch (RehearsalIdempotencyConflictException $expected) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, $this->rehearsalCount($production));
        $this->assertSame($notified, count($this->notifications->sent()));
    }

    public function test_different_targets_with_the_same_key_is_a_conflict(): void
    {
        $production = $this->givenProduction(1);
        $a = $this->addMember($production, 2);
        $b = $this->addMember($production, 3);
        $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1', ['persons' => [$a->id()->toString()]]));

        $this->expectException(RehearsalIdempotencyConflictException::class);

        $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1', ['persons' => [$a->id()->toString(), $b->id()->toString()]]));
    }

    public function test_a_different_key_creates_another_rehearsal(): void
    {
        $production = $this->givenProduction(1);

        $first = $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1'));
        $second = $this->createRehearsal->executeWithOutcome($this->command($production, 'key-2'));

        $this->assertFalse($second->replayed);
        $this->assertNotSame($first->result->id, $second->result->id);
        $this->assertSame(2, $this->rehearsalCount($production));
    }

    public function test_another_manager_reusing_a_key_gets_a_conflict(): void
    {
        $production = $this->givenProduction(1);
        $this->addRehearsalManager($production, 5);
        $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1'));

        $this->expectException(RehearsalIdempotencyConflictException::class);

        $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1', ['user' => 5]));
    }

    public function test_keys_are_scoped_to_the_production(): void
    {
        $first = $this->givenProduction(1);
        $second = $this->givenProduction(9);

        $a = $this->createRehearsal->executeWithOutcome($this->command($first, 'shared-key'));
        $b = $this->createRehearsal->executeWithOutcome($this->command($second, 'shared-key', ['user' => 9]));

        $this->assertFalse($b->replayed);
        $this->assertNotSame($a->result->id, $b->result->id);
        $this->assertSame(1, $this->rehearsalCount($first));
        $this->assertSame(1, $this->rehearsalCount($second));
    }

    public function test_a_member_without_permission_cannot_use_someone_elses_key(): void
    {
        $production = $this->givenProduction(1);
        $this->addMember($production, 2);
        $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1'));

        $this->expectException(RehearsalAccessDeniedException::class);

        $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1', ['user' => 2]));
    }

    /**
     * Two requests with the same key at once: the other one commits its
     * key while this one is about to record it (as when this INSERT waits
     * on the other transaction's UNIQUE key). This one rolls back, creates
     * nothing, notifies nobody, and answers with the other's Rehearsal.
     */
    public function test_a_concurrent_request_with_the_same_key_gets_the_first_rehearsal(): void
    {
        $production = $this->givenProduction(1);
        $a = $this->addMember($production, 2);
        $manager = $this->people->findByWordPressUserId(1);
        $zone = new \DateTimeZone('Asia/Tokyo');
        $start = new \DateTimeImmutable('2099-08-20T18:00:00+09:00');
        $end = new \DateTimeImmutable('2099-08-20T21:00:00+09:00');
        $deadline = new \DateTimeImmutable('2099-08-18T12:00:00+09:00');
        $winner = Rehearsal::create($production->id(), '通し稽古', null, $start->setTimezone($zone), $end->setTimezone($zone), 'Asia/Tokyo', 'Studio A', $deadline->setTimezone($zone));
        $sameRequestHash = RehearsalCreateRequestHash::hash(RehearsalCreateRequestHash::normalize(
            $production->id(), '通し稽古', null, $start->setTimezone($zone), $end->setTimezone($zone), $deadline->setTimezone($zone),
            'Asia/Tokyo', 'Studio A', [$a->id()->toString()], null, false
        ));

        // The other request commits the same key (and its Rehearsal) while
        // this one is about to record it.
        $this->createRequests->beforeRecord(function () use ($production, $winner, $manager, $sameRequestHash): void {
            $this->rehearsals->save($winner);
            $this->createRequests->seed($production->id(), 'key-1', new RehearsalCreateRequestRecord($manager->id(), $sameRequestHash, $winner->id()));
        });

        $loser = $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1', ['persons' => [$a->id()->toString()]]));

        $this->assertTrue($loser->replayed);
        $this->assertSame($winner->id()->toString(), $loser->result->id);
        $this->assertSame(1, $this->rehearsalCount($production), 'This request created nothing.');
        $this->assertSame(1, $this->createRequests->count());
        $this->assertSame([], $this->notifications->sent(), 'This request notified nobody.');
        $this->assertSame(1, $this->transactions->rollbacks());
        $this->assertSame([], $this->attendances->findByRehearsalIdAndPhase($winner->id(), \StageArt\Domain\RehearsalAttendance\RehearsalAttendancePhase::scheduleAdjustment()), 'This request wrote no attendance rows.');
    }

    public function test_a_concurrent_different_request_with_the_same_key_is_a_conflict(): void
    {
        $production = $this->givenProduction(1);
        $other = Rehearsal::create($production->id(), 'other', null, new \DateTimeImmutable('2099-08-21T10:00:00+09:00'), new \DateTimeImmutable('2099-08-21T12:00:00+09:00'), 'Asia/Tokyo', null);
        $this->rehearsals->save($other);
        $manager = $this->people->findByWordPressUserId(1);

        $this->createRequests->beforeRecord(function () use ($production, $other, $manager): void {
            $this->createRequests->seed($production->id(), 'key-1', new RehearsalCreateRequestRecord($manager->id(), str_repeat('0', 64), $other->id()));
        });

        try {
            $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1'));
            $this->fail('A different concurrent request with the same key was accepted.');
        } catch (RehearsalIdempotencyConflictException $expected) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(1, $this->rehearsalCount($production), 'Only the other request\'s Rehearsal exists.');
        $this->assertSame([], $this->notifications->sent());
        $this->assertSame(1, $this->transactions->rollbacks());
    }

    public function test_a_database_error_on_the_key_is_not_treated_as_a_duplicate(): void
    {
        $production = $this->givenProduction(1);
        $this->createRequests->failNextRecord();

        try {
            $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1'));
            $this->fail('A database error was swallowed.');
        } catch (RuntimeException $expected) {
            $this->assertStringContainsString('Simulated database error', $expected->getMessage());
        }

        $this->assertSame(0, $this->rehearsalCount($production));
        $this->assertSame(0, $this->createRequests->count());
        $this->assertSame([], $this->notifications->sent());

        // Sending it again works and creates exactly one.
        $this->createRehearsal->executeWithOutcome($this->command($production, 'key-1'));
        $this->assertSame(1, $this->rehearsalCount($production));
    }

    public function test_without_a_key_every_request_creates_a_rehearsal_as_before(): void
    {
        $production = $this->givenProduction(1);

        $this->createRehearsal->executeWithOutcome($this->command($production, null));
        $this->createRehearsal->executeWithOutcome($this->command($production, null));
        $this->createRehearsal->executeWithOutcome($this->command($production, ''));

        $this->assertSame(3, $this->rehearsalCount($production));
        $this->assertSame(0, $this->createRequests->count());
    }

    /**
     * @return array<string, array{string}>
     */
    public function invalidKeys(): array
    {
        return ['space' => ['a b'], 'too long' => [str_repeat('k', 101)], 'slash' => ['a/b'], 'japanese' => ['キー']];
    }

    /**
     * @dataProvider invalidKeys
     */
    public function test_an_invalid_key_is_rejected(string $key): void
    {
        $production = $this->givenProduction(1);

        try {
            $this->createRehearsal->executeWithOutcome($this->command($production, $key));
            $this->fail('An invalid key was accepted.');
        } catch (InvalidArgumentException $expected) {
            $this->assertStringContainsString('idempotency_key', $expected->getMessage());
        }

        $this->assertSame(0, $this->rehearsalCount($production));
    }

    public function test_a_uuid_key_is_accepted(): void
    {
        $production = $this->givenProduction(1);

        $outcome = $this->createRehearsal->executeWithOutcome($this->command($production, '3f6f93e8-3d10-460e-8c73-743ee690fc64'));

        $this->assertFalse($outcome->replayed);
    }
}
