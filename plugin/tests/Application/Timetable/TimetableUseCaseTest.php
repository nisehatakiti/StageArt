<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Timetable;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Timetable\CreateNewTimetableVersionCommand;
use StageArt\Application\Timetable\CreateNewTimetableVersionUseCase;
use StageArt\Application\Timetable\GetDraftTimetableQuery;
use StageArt\Application\Timetable\GetDraftTimetableUseCase;
use StageArt\Application\Timetable\GetTimetableQuery;
use StageArt\Application\Timetable\GetTimetableUseCase;
use StageArt\Application\Timetable\ListTimetableVersionsQuery;
use StageArt\Application\Timetable\ListTimetableVersionsUseCase;
use StageArt\Application\Timetable\NextTimetableVersionResolver;
use StageArt\Application\Timetable\PublishTimetableVersionCommand;
use StageArt\Application\Timetable\PublishTimetableVersionUseCase;
use StageArt\Application\Timetable\TimetableAccessDeniedException;
use StageArt\Application\Timetable\TimetableDraftAlreadyExistsException;
use StageArt\Application\Timetable\TimetableNotFoundException;
use StageArt\Application\Timetable\TimetableVersionRequiredException;
use StageArt\Application\TimetableItem\CreateTimetableItemCommand;
use StageArt\Application\TimetableItem\CreateTimetableItemUseCase;
use StageArt\Application\TimetableItem\ListDraftTimetableItemsQuery;
use StageArt\Application\TimetableItem\ListDraftTimetableItemsUseCase;
use StageArt\Application\TimetableItem\ListTimetableItemsQuery;
use StageArt\Application\TimetableItem\ListTimetableItemsUseCase;
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

final class TimetableUseCaseTest extends TestCase
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
    private InMemoryTimetableVersionPublishedNotificationRepository $notifications;

    private GetTimetableUseCase $getTimetable;
    private GetDraftTimetableUseCase $getDraftTimetable;
    private ListTimetableVersionsUseCase $listVersions;
    private CreateNewTimetableVersionUseCase $createNewVersion;
    private PublishTimetableVersionUseCase $publishVersion;
    private CreateTimetableItemUseCase $createItem;
    private ListTimetableItemsUseCase $listItems;
    private ListDraftTimetableItemsUseCase $listDraftItems;

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
        $this->notifications = new InMemoryTimetableVersionPublishedNotificationRepository();

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

        $this->getTimetable = new GetTimetableUseCase($this->timetables, $this->rehearsals, $productionContext, $identity, $membership);
        $this->getDraftTimetable = new GetDraftTimetableUseCase(
            $this->timetables,
            $this->rehearsals,
            $productionContext,
            $identity,
            $membership
        );
        $this->listVersions = new ListTimetableVersionsUseCase(
            $this->timetables,
            $this->rehearsals,
            $productionContext,
            $identity,
            $membership
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
        $this->publishVersion = new PublishTimetableVersionUseCase(
            $this->timetables,
            $this->rehearsals,
            $productionContext,
            $this->notifications,
            $membership,
            $notificationContract,
            $identity,
            $authorization,
            $transactions
        );
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
        $this->listItems = new ListTimetableItemsUseCase(
            $this->rehearsals,
            $productionContext,
            $this->timetables,
            $this->items,
            $identity,
            $membership
        );
        $this->listDraftItems = new ListDraftTimetableItemsUseCase(
            $this->rehearsals,
            $productionContext,
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

        $production = Production::create($project->id(), new ProductionName('Show'), $primaryManager->id());
        $this->productions->save($production);

        return $production;
    }

    private function addActivePersonParticipant(Production $production, int $wordPressUserId): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);

        $this->participants->save(Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            ParticipantType::cast()
        ));

        return $person;
    }

    private function givenRehearsal(Production $production): Rehearsal
    {
        $rehearsal = Rehearsal::create($production->id(), 'Act 1', null, null, null, null, null);
        $this->rehearsals->save($rehearsal);

        return $rehearsal;
    }

    private function addItem(string $rehearsalId, int $actorWordPressUserId, string $title): string
    {
        return $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsalId,
            $actorWordPressUserId,
            $title,
            null,
            '2026-10-29T10:00:00+09:00',
            null,
            null,
            null,
            null,
            null,
            [],
            null
        ))->id;
    }

    public function test_no_published_timetable_before_first_publish(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->addItem($rehearsal->id()->toString(), 1, '搬入');

        $this->expectException(TimetableNotFoundException::class);
        $this->getTimetable->execute(new GetTimetableQuery($rehearsal->id()->toString(), 1));
    }

    public function test_draft_visible_before_publish(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->addItem($rehearsal->id()->toString(), 1, '搬入');

        $draft = $this->getDraftTimetable->execute(new GetDraftTimetableQuery($rehearsal->id()->toString(), 1));

        $this->assertSame(1, $draft->version);
        $this->assertSame('DRAFT', $draft->status);
    }

    public function test_publish_makes_it_the_official_version(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->addItem($rehearsal->id()->toString(), 1, '搬入');

        $draftBefore = $this->getDraftTimetable->execute(new GetDraftTimetableQuery($rehearsal->id()->toString(), 1));

        $published = $this->publishVersion->execute(
            new PublishTimetableVersionCommand($draftBefore->id, 1, '初回公開')
        );

        $this->assertSame('PUBLISHED', $published->status);
        $this->assertSame(1, $published->version);
        $this->assertSame('初回公開', $published->changeSummary);
        $this->assertNotNull($published->publishedAt);

        $current = $this->getTimetable->execute(new GetTimetableQuery($rehearsal->id()->toString(), 1));
        $this->assertSame($published->id, $current->id);

        // No Draft remains after publishing.
        $this->expectException(TimetableNotFoundException::class);
        $this->getDraftTimetable->execute(new GetDraftTimetableQuery($rehearsal->id()->toString(), 1));
    }

    public function test_items_move_from_draft_only_to_published_list_after_publish(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->addItem($rehearsal->id()->toString(), 1, '搬入');

        $beforePublishOfficial = $this->listItems->execute(new ListTimetableItemsQuery($rehearsal->id()->toString(), 1));
        $this->assertCount(0, $beforePublishOfficial, 'A DRAFT-only Item must not appear in the official (Published) list yet.');

        $beforePublishDraft = $this->listDraftItems->execute(new ListDraftTimetableItemsQuery($rehearsal->id()->toString(), 1));
        $this->assertCount(1, $beforePublishDraft);

        $draft = $this->getDraftTimetable->execute(new GetDraftTimetableQuery($rehearsal->id()->toString(), 1));
        $this->publishVersion->execute(new PublishTimetableVersionCommand($draft->id, 1, null));

        $afterPublishOfficial = $this->listItems->execute(new ListTimetableItemsQuery($rehearsal->id()->toString(), 1));
        $this->assertCount(1, $afterPublishOfficial);
    }

    public function test_cannot_add_item_directly_when_published_exists_without_draft(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->addItem($rehearsal->id()->toString(), 1, '搬入');
        $draft = $this->getDraftTimetable->execute(new GetDraftTimetableQuery($rehearsal->id()->toString(), 1));
        $this->publishVersion->execute(new PublishTimetableVersionCommand($draft->id, 1, null));

        $this->expectException(TimetableVersionRequiredException::class);
        $this->addItem($rehearsal->id()->toString(), 1, '追加アイテム');
    }

    public function test_create_new_version_copies_published_items_independently(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->addItem($rehearsal->id()->toString(), 1, '搬入');
        $draftV1 = $this->getDraftTimetable->execute(new GetDraftTimetableQuery($rehearsal->id()->toString(), 1));
        $this->publishVersion->execute(new PublishTimetableVersionCommand($draftV1->id, 1, null));

        $newVersion = $this->createNewVersion->execute(new CreateNewTimetableVersionCommand($rehearsal->id()->toString(), 1));

        $this->assertSame(2, $newVersion->version);
        $this->assertSame('DRAFT', $newVersion->status);

        $draftItems = $this->listDraftItems->execute(new ListDraftTimetableItemsQuery($rehearsal->id()->toString(), 1));
        $this->assertCount(1, $draftItems, 'The new DRAFT must start with a copy of the Published Version\'s Items.');
        $this->assertSame('搬入', $draftItems[0]->title);

        // The copy has a new Identity, distinct from the original.
        $publishedItems = $this->listItems->execute(new ListTimetableItemsQuery($rehearsal->id()->toString(), 1));
        $this->assertNotSame($publishedItems[0]->id, $draftItems[0]->id);

        // Editing the new Draft (e.g. via update in a real flow) never touches v1's own item,
        // demonstrated here simply by confirming v1's published list is untouched.
        $stillOneOfficialItem = $this->listItems->execute(new ListTimetableItemsQuery($rehearsal->id()->toString(), 1));
        $this->assertCount(1, $stillOneOfficialItem);
    }

    public function test_publishing_new_version_archives_the_old_one(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->addItem($rehearsal->id()->toString(), 1, '搬入');
        $draftV1 = $this->getDraftTimetable->execute(new GetDraftTimetableQuery($rehearsal->id()->toString(), 1));
        $v1 = $this->publishVersion->execute(new PublishTimetableVersionCommand($draftV1->id, 1, null));

        $v2Draft = $this->createNewVersion->execute(new CreateNewTimetableVersionCommand($rehearsal->id()->toString(), 1));
        $v2 = $this->publishVersion->execute(new PublishTimetableVersionCommand($v2Draft->id, 1, '2回目の公開'));

        $this->assertSame('PUBLISHED', $v2->status);

        $history = $this->listVersions->execute(new ListTimetableVersionsQuery($rehearsal->id()->toString(), 1));
        $this->assertCount(2, $history);

        $historyById = [];
        foreach ($history as $entry) {
            $historyById[$entry->id] = $entry;
        }
        $this->assertSame('ARCHIVED', $historyById[$v1->id]->status);
        $this->assertSame('PUBLISHED', $historyById[$v2->id]->status);

        // v1's publishedBy/publishedAt survive archiving unchanged.
        $this->assertNotNull($historyById[$v1->id]->publishedAt);

        $current = $this->getTimetable->execute(new GetTimetableQuery($rehearsal->id()->toString(), 1));
        $this->assertSame($v2->id, $current->id);
    }

    public function test_cannot_create_new_version_while_a_draft_already_exists(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->addItem($rehearsal->id()->toString(), 1, '搬入'); // creates DRAFT v1

        $this->expectException(TimetableDraftAlreadyExistsException::class);
        $this->createNewVersion->execute(new CreateNewTimetableVersionCommand($rehearsal->id()->toString(), 1));
    }

    public function test_non_manager_cannot_create_new_version_or_publish(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->addActivePersonParticipant($production, 2);
        $this->addItem($rehearsal->id()->toString(), 1, '搬入');
        $draft = $this->getDraftTimetable->execute(new GetDraftTimetableQuery($rehearsal->id()->toString(), 1));

        try {
            $this->publishVersion->execute(new PublishTimetableVersionCommand($draft->id, 2, null));
            $this->fail('Expected TimetableAccessDeniedException.');
        } catch (TimetableAccessDeniedException $exception) {
            // expected
        }

        $this->publishVersion->execute(new PublishTimetableVersionCommand($draft->id, 1, null));

        $this->expectException(TimetableAccessDeniedException::class);
        $this->createNewVersion->execute(new CreateNewTimetableVersionCommand($rehearsal->id()->toString(), 2));
    }

    public function test_non_member_cannot_view_versions_or_history(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->givenProductionWithPrimaryManager(99);
        $this->addItem($rehearsal->id()->toString(), 1, '搬入');

        $this->expectException(TimetableAccessDeniedException::class);
        $this->listVersions->execute(new ListTimetableVersionsQuery($rehearsal->id()->toString(), 99));
    }

    public function test_publish_records_a_notification(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->addItem($rehearsal->id()->toString(), 1, '搬入');
        $draft = $this->getDraftTimetable->execute(new GetDraftTimetableQuery($rehearsal->id()->toString(), 1));

        $this->publishVersion->execute(new PublishTimetableVersionCommand($draft->id, 1, '照明シュートを追加'));

        $notifications = $this->notifications->findByProductionId($production->id());
        $this->assertCount(1, $notifications);
        $this->assertSame(1, $notifications[0]->version());
        $this->assertSame('照明シュートを追加', $notifications[0]->changeSummary());
        $this->assertTrue($notifications[0]->rehearsalId()->equals($rehearsal->id()));
    }
}
