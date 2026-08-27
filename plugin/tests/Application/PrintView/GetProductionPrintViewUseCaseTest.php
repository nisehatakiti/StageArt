<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\PrintView;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\PrintView\GetProductionPrintViewUseCase;
use StageArt\Application\PrintView\PrintViewAccessDeniedException;
use StageArt\Application\PrintView\ProductionPrintViewQuery;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Timetable\CreateNewTimetableVersionCommand;
use StageArt\Application\Timetable\CreateNewTimetableVersionUseCase;
use StageArt\Application\Timetable\NextTimetableVersionResolver;
use StageArt\Application\Timetable\PublishTimetableVersionCommand;
use StageArt\Application\Timetable\PublishTimetableVersionUseCase;
use StageArt\Application\TimetableItem\CreateTimetableItemCommand;
use StageArt\Application\TimetableItem\CreateTimetableItemUseCase;
use StageArt\Application\TimetableItem\TimetableItemTargetValidator;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreNotificationAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Tests\Support\InMemoryNotificationDispatcher;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryRehearsalRepository;
use StageArt\Tests\Support\InMemoryTimetableItemRepository;
use StageArt\Tests\Support\InMemoryTimetableRepository;
use StageArt\Tests\Support\InMemoryTimetableVersionPublishedNotificationRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class GetProductionPrintViewUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private InMemoryRehearsalRepository $rehearsals;
    private InMemoryTimetableRepository $timetables;
    private InMemoryTimetableItemRepository $items;

    private CreateTimetableItemUseCase $createItem;
    private PublishTimetableVersionUseCase $publishVersion;
    private CreateNewTimetableVersionUseCase $createNewVersion;
    private GetProductionPrintViewUseCase $getPrintView;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->rehearsals = new InMemoryRehearsalRepository();
        $this->timetables = new InMemoryTimetableRepository();
        $this->items = new InMemoryTimetableItemRepository();
        $notifications = new InMemoryTimetableVersionPublishedNotificationRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            $this->participants
        );
        $transactions = new InMemoryTransactionManager();
        $versionResolver = new NextTimetableVersionResolver($this->timetables);
        $membership = new CoreMembershipAdapter($this->participants, $this->productions, $this->people, $productionAuthorization);
        $productionContext = new CoreProductionContextAdapter($this->productions, new ProductionOrganizationResolver(new InMemoryProjectRepository()));
        $identity = new CoreIdentityAdapter($this->people);
        $authorization = new CoreAuthorizationAdapter($productionAuthorization, $this->productions, $this->people);
        $notificationContract = new CoreNotificationAdapter(new InMemoryNotificationDispatcher());

        $this->createItem = new CreateTimetableItemUseCase(
            $this->rehearsals,
            $productionContext,
            $this->timetables,
            $this->items,
            new TimetableItemTargetValidator($membership),
            $versionResolver,
            $identity,
            $authorization,
            $transactions
        );
        $this->publishVersion = new PublishTimetableVersionUseCase(
            $this->timetables,
            $this->rehearsals,
            $productionContext,
            $notifications,
            $membership,
            $notificationContract,
            $identity,
            $authorization,
            $transactions
        );
        $this->createNewVersion = new CreateNewTimetableVersionUseCase(
            $this->rehearsals,
            $productionContext,
            $this->timetables,
            $this->items,
            $versionResolver,
            $identity,
            $authorization,
            $transactions
        );
        $this->getPrintView = new GetProductionPrintViewUseCase(
            $productionContext,
            $this->rehearsals,
            $this->timetables,
            $this->items,
            $identity,
            $membership
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

        $production = Production::create($project->id(), new ProductionName('Print View Show'), $primaryManager->id());
        $this->productions->save($production);

        return $production;
    }

    private function addActivePersonParticipant(Production $production, int $wordPressUserId, ParticipantType $type): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);

        $this->participants->save(Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            $type
        ));

        return $person;
    }

    private function givenRehearsal(Production $production, string $title): Rehearsal
    {
        $rehearsal = Rehearsal::create($production->id(), $title, null, null, null, null, null);
        $this->rehearsals->save($rehearsal);

        return $rehearsal;
    }

    private function addAndPublishItem(
        string $rehearsalId,
        int $actorWordPressUserId,
        string $title,
        string $startDateTime,
        ?string $participantType = null,
        array $targetPersonIds = [],
        ?string $venue = null,
        ?string $category = null
    ): void {
        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsalId,
            $actorWordPressUserId,
            $title,
            null,
            $startDateTime,
            null,
            null,
            $category,
            $venue,
            $participantType,
            $targetPersonIds,
            null
        ));

        $draft = $this->timetables->findDraftByRehearsalId(
            \StageArt\Domain\Rehearsal\RehearsalId::fromString($rehearsalId)
        );

        $this->publishVersion->execute(new PublishTimetableVersionCommand($draft->id()->toString(), $actorWordPressUserId, null));
    }

    public function test_only_published_items_appear_never_draft(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production, '仕込み');
        $this->addAndPublishItem($rehearsal->id()->toString(), 1, '搬入', '2026-10-28T09:00:00+09:00');

        // Explicitly create a new (v2) Draft and add an item to it, but
        // never publish it - it must not appear in the Print View, which
        // must keep showing v1's item only.
        $this->createNewVersion->execute(new CreateNewTimetableVersionCommand($rehearsal->id()->toString(), 1));
        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            1,
            '未公開の項目',
            null,
            '2026-10-28T10:00:00+09:00',
            null,
            null,
            null,
            null,
            null,
            [],
            null
        ));

        $result = $this->getPrintView->execute(new ProductionPrintViewQuery($production->id()->toString(), 1));

        $this->assertCount(1, $result->sections);
        $this->assertCount(1, $result->sections[0]->items);
        $this->assertSame('搬入', $result->sections[0]->items[0]->title);
        $this->assertSame(1, $result->sections[0]->timetableVersion);
    }

    public function test_rehearsal_with_no_published_version_is_omitted(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->givenRehearsal($production, '予定のみのRehearsal');

        $result = $this->getPrintView->execute(new ProductionPrintViewQuery($production->id()->toString(), 1));

        $this->assertCount(0, $result->sections);
    }

    public function test_aggregates_across_multiple_rehearsals(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsalA = $this->givenRehearsal($production, '搬入日');
        $rehearsalB = $this->givenRehearsal($production, '本番日');
        $this->addAndPublishItem($rehearsalA->id()->toString(), 1, '搬入', '2026-10-28T09:00:00+09:00');
        $this->addAndPublishItem($rehearsalB->id()->toString(), 1, '本番', '2026-10-30T18:00:00+09:00');

        $result = $this->getPrintView->execute(new ProductionPrintViewQuery($production->id()->toString(), 1));

        $this->assertCount(2, $result->sections);
    }

    /**
     * Phase 4.5 §6: no Role-based filtering anywhere - a CAST member and
     * a STAFF member of the same Production must receive identical data.
     */
    public function test_no_role_filtering_across_participant_types(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production, '仕込み');
        $this->addAndPublishItem(
            $rehearsal->id()->toString(),
            1,
            '照明シュート',
            '2026-10-28T10:00:00+09:00',
            ParticipantType::STAFF
        );
        $cast = $this->addActivePersonParticipant($production, 2, ParticipantType::cast());
        $staff = $this->addActivePersonParticipant($production, 3, ParticipantType::staff());

        $castResult = $this->getPrintView->execute(new ProductionPrintViewQuery($production->id()->toString(), 2));
        $staffResult = $this->getPrintView->execute(new ProductionPrintViewQuery($production->id()->toString(), 3));

        $this->assertEquals($castResult->toArray(), $staffResult->toArray());
    }

    public function test_multiple_items_at_the_same_time_coexist(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production, '仕込み');

        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            1,
            '音響BC',
            null,
            '2026-10-28T10:00:00+09:00',
            null,
            null,
            null,
            null,
            null,
            [],
            null
        ));
        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            1,
            '照明シュート',
            null,
            '2026-10-28T10:00:00+09:00',
            null,
            null,
            null,
            null,
            null,
            [],
            null
        ));

        $draft = $this->timetables->findDraftByRehearsalId($rehearsal->id());
        $this->publishVersion->execute(new PublishTimetableVersionCommand($draft->id()->toString(), 1, null));

        $result = $this->getPrintView->execute(new ProductionPrintViewQuery($production->id()->toString(), 1));

        $this->assertCount(1, $result->sections);
        $this->assertCount(2, $result->sections[0]->items);
    }

    /**
     * Phase 4.5 §16/§18: Version and Published-at must be identifiable
     * per printed Rehearsal section.
     */
    public function test_section_carries_version_and_published_at(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production, '仕込み');
        $this->addAndPublishItem($rehearsal->id()->toString(), 1, '搬入', '2026-10-28T09:00:00+09:00');

        $result = $this->getPrintView->execute(new ProductionPrintViewQuery($production->id()->toString(), 1));

        $this->assertSame(1, $result->sections[0]->timetableVersion);
        $this->assertNotNull($result->sections[0]->publishedAt);
    }

    /**
     * Phase 4.5 §7: Stage Usage has no dedicated field - Venue is the
     * signal, and it must be present in the printed data (never
     * stripped/hidden for any viewer).
     */
    public function test_venue_information_is_present_for_stage_usage(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production, '仕込み');
        $this->addAndPublishItem(
            $rehearsal->id()->toString(),
            1,
            '照明シュート',
            '2026-10-28T10:00:00+09:00',
            null,
            [],
            '舞台'
        );

        $result = $this->getPrintView->execute(new ProductionPrintViewQuery($production->id()->toString(), 1));

        $this->assertSame('舞台', $result->sections[0]->items[0]->venue);
    }

    public function test_category_free_text_is_preserved(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production, '仕込み');
        $this->addAndPublishItem(
            $rehearsal->id()->toString(),
            1,
            '初日ご挨拶',
            '2026-10-31T12:00:00+09:00',
            null,
            [],
            null,
            '団体独自カテゴリ'
        );

        $result = $this->getPrintView->execute(new ProductionPrintViewQuery($production->id()->toString(), 1));

        $this->assertSame('団体独自カテゴリ', $result->sections[0]->items[0]->category);
    }

    /**
     * Phase 4.5 §6: Role-only / Person-only / Role+Person all pass
     * through the Print View aggregation unchanged (already an
     * independently-tested Domain pattern; this is Print View's own
     * end-to-end confirmation).
     */
    public function test_role_only_person_only_and_role_and_person_patterns(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production, '仕込み');
        $target = $this->addActivePersonParticipant($production, 9, ParticipantType::cast());

        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(), 1, 'Role-only', null, '2026-10-28T09:00:00+09:00',
            null, null, null, null, ParticipantType::STAFF, [], null
        ));
        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(), 1, 'Person-only', null, '2026-10-28T09:30:00+09:00',
            null, null, null, null, null, [$target->id()->toString()], null
        ));
        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(), 1, 'Role+Person', null, '2026-10-28T10:00:00+09:00',
            null, null, null, null, ParticipantType::CAST, [$target->id()->toString()], null
        ));

        $draft = $this->timetables->findDraftByRehearsalId($rehearsal->id());
        $this->publishVersion->execute(new PublishTimetableVersionCommand($draft->id()->toString(), 1, null));

        $result = $this->getPrintView->execute(new ProductionPrintViewQuery($production->id()->toString(), 1));
        $items = $result->sections[0]->items;

        $this->assertSame('STAFF', $items[0]->participantType);
        $this->assertSame([], $items[0]->targetPersonIds);

        $this->assertNull($items[1]->participantType);
        $this->assertCount(1, $items[1]->targetPersonIds);

        $this->assertSame('CAST', $items[2]->participantType);
        $this->assertCount(1, $items[2]->targetPersonIds);
    }

    public function test_non_member_is_denied(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $outsider = Person::create(99);
        $this->people->save($outsider);

        $this->expectException(PrintViewAccessDeniedException::class);

        $this->getPrintView->execute(new ProductionPrintViewQuery($production->id()->toString(), 99));
    }

    public function test_no_linked_person_is_denied(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $this->expectException(PrintViewAccessDeniedException::class);

        $this->getPrintView->execute(new ProductionPrintViewQuery($production->id()->toString(), 12345));
    }
}
