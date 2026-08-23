<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\TimetableItem;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Timetable\NextTimetableVersionResolver;
use StageArt\Application\Timetable\PublishTimetableVersionCommand;
use StageArt\Application\Timetable\PublishTimetableVersionUseCase;
use StageArt\Application\TimetableItem\CreateTimetableItemCommand;
use StageArt\Application\TimetableItem\CreateTimetableItemUseCase;
use StageArt\Application\TimetableItem\DeleteTimetableItemCommand;
use StageArt\Application\TimetableItem\DeleteTimetableItemUseCase;
use StageArt\Application\TimetableItem\GetTimetableItemQuery;
use StageArt\Application\TimetableItem\GetTimetableItemUseCase;
use StageArt\Application\TimetableItem\ListTimetableItemsQuery;
use StageArt\Application\TimetableItem\ListTimetableItemsUseCase;
use StageArt\Application\TimetableItem\TimetableItemAccessDeniedException;
use StageArt\Application\TimetableItem\TimetableItemInvalidTargetException;
use StageArt\Application\TimetableItem\TimetableItemNotFoundException;
use StageArt\Application\TimetableItem\TimetableItemTargetValidator;
use StageArt\Application\TimetableItem\UpdateTimetableItemCommand;
use StageArt\Application\TimetableItem\UpdateTimetableItemUseCase;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Rehearsal\Rehearsal;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryRehearsalRepository;
use StageArt\Tests\Support\InMemoryTimetableItemRepository;
use StageArt\Tests\Support\InMemoryTimetableRepository;
use StageArt\Tests\Support\InMemoryTimetableVersionPublishedNotificationRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class TimetableItemUseCaseTest extends TestCase
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
    private GetTimetableItemUseCase $getItem;
    private ListTimetableItemsUseCase $listItems;
    private UpdateTimetableItemUseCase $updateItem;
    private DeleteTimetableItemUseCase $deleteItem;
    private PublishTimetableVersionUseCase $publishVersion;

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

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            $this->participants
        );
        $targetValidator = new TimetableItemTargetValidator($this->participants);
        $transactions = new InMemoryTransactionManager();
        $versionResolver = new NextTimetableVersionResolver($this->timetables);

        $this->createItem = new CreateTimetableItemUseCase(
            $this->rehearsals,
            $this->productions,
            $this->timetables,
            $this->items,
            $targetValidator,
            $versionResolver,
            $productionAuthorization,
            $transactions
        );
        $this->publishVersion = new PublishTimetableVersionUseCase(
            $this->timetables,
            $this->rehearsals,
            $this->productions,
            new InMemoryTimetableVersionPublishedNotificationRepository(),
            $productionAuthorization,
            $transactions
        );
        $this->getItem = new GetTimetableItemUseCase(
            $this->items,
            $this->timetables,
            $this->rehearsals,
            $this->productions,
            $productionAuthorization
        );
        $this->listItems = new ListTimetableItemsUseCase(
            $this->rehearsals,
            $this->productions,
            $this->timetables,
            $this->items,
            $productionAuthorization
        );
        $this->updateItem = new UpdateTimetableItemUseCase(
            $this->items,
            $this->timetables,
            $this->rehearsals,
            $this->productions,
            $targetValidator,
            $productionAuthorization,
            $transactions
        );
        $this->deleteItem = new DeleteTimetableItemUseCase(
            $this->items,
            $this->timetables,
            $this->rehearsals,
            $this->productions,
            $productionAuthorization
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

    private function addActivePersonParticipant(Production $production, int $wordPressUserId, string $participantType = ParticipantType::CAST): Person
    {
        $person = Person::create($wordPressUserId);
        $this->people->save($person);

        $participant = Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            ParticipantType::fromString($participantType)
        );
        $this->participants->save($participant);

        return $person;
    }

    private function givenRehearsal(Production $production): Rehearsal
    {
        $rehearsal = Rehearsal::create($production->id(), 'Act 1', null, null, null, null, null);
        $this->rehearsals->save($rehearsal);

        return $rehearsal;
    }

    /**
     * ListTimetableItemsUseCase now reflects the current PUBLISHED
     * Version only - tests that need to see Items through that "official"
     * list must publish the DRAFT they were added to first.
     */
    private function publishDraft(RehearsalId $rehearsalId, int $publisherWordPressUserId): void
    {
        $draft = $this->timetables->findDraftByRehearsalId($rehearsalId);
        $this->publishVersion->execute(new PublishTimetableVersionCommand($draft->id()->toString(), $publisherWordPressUserId, null));
    }

    public function test_manager_can_create_timetable_item_and_timetable_is_created_implicitly(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);

        $result = $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            1,
            '照明シュート',
            null,
            '2026-10-29T10:00:00+09:00',
            '2026-10-29T12:00:00+09:00',
            1,
            'TECHNICAL',
            '舞台',
            null,
            [],
            null
        ));

        $this->assertSame('照明シュート', $result->title);
        $draft = $this->timetables->findDraftByRehearsalId($rehearsal->id());
        $this->assertNotNull($draft);
        $this->assertSame(1, $draft->version());
    }

    public function test_plain_member_cannot_create_timetable_item(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->addActivePersonParticipant($production, 2);

        $this->expectException(TimetableItemAccessDeniedException::class);

        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            2,
            '発声',
            null,
            '2026-10-29T19:00:00+09:00',
            null,
            null,
            null,
            null,
            null,
            [],
            null
        ));
    }

    public function test_create_rejects_target_person_not_a_participant(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $notAParticipant = Person::create(99);
        $this->people->save($notAParticipant);

        $this->expectException(TimetableItemInvalidTargetException::class);

        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            1,
            '第一場',
            null,
            '2026-10-29T19:30:00+09:00',
            null,
            null,
            null,
            null,
            null,
            [$notAParticipant->id()->toString()],
            null
        ));
    }

    /**
     * The central Shared Visibility test: three Production Members with
     * three different Participant Types (CAST, STAFF, and the
     * PrimaryManager itself) all issue the same List request and must
     * all receive the exact same TimetableItem set - there is no
     * per-Role filtering anywhere in ListTimetableItemsUseCase.
     */
    public function test_all_members_regardless_of_participant_type_see_the_same_item_set(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $castMember = $this->addActivePersonParticipant($production, 2, ParticipantType::CAST);
        $staffMember = $this->addActivePersonParticipant($production, 3, ParticipantType::STAFF);

        // A lighting-only item, targeted only at STAFF, created by the manager.
        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            1,
            '照明シュート',
            null,
            '2026-10-29T10:00:00+09:00',
            '2026-10-29T12:00:00+09:00',
            null,
            'TECHNICAL',
            '舞台',
            ParticipantType::STAFF,
            [$staffMember->id()->toString()],
            null
        ));

        // A cast-only item.
        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            1,
            '発声練習',
            null,
            '2026-10-29T19:00:00+09:00',
            null,
            null,
            null,
            null,
            ParticipantType::CAST,
            [$castMember->id()->toString()],
            null
        ));

        $this->publishDraft($rehearsal->id(), 1);

        $asPrimaryManager = $this->listItems->execute(new ListTimetableItemsQuery($rehearsal->id()->toString(), 1));
        $asCast = $this->listItems->execute(new ListTimetableItemsQuery($rehearsal->id()->toString(), 2));
        $asStaff = $this->listItems->execute(new ListTimetableItemsQuery($rehearsal->id()->toString(), 3));

        $this->assertCount(2, $asPrimaryManager);
        $this->assertCount(2, $asCast, 'A CAST member must also see the STAFF-only item - Shared Visibility, not Role filtering.');
        $this->assertCount(2, $asStaff, 'A STAFF member must also see the CAST-only item.');

        $titlesForCast = array_map(static fn ($item) => $item->title, $asCast);
        $this->assertEqualsCanonicalizing(['発声練習', '照明シュート'], $titlesForCast);
    }

    public function test_list_returns_empty_array_when_no_timetable_exists_yet(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->addActivePersonParticipant($production, 2);

        $result = $this->listItems->execute(new ListTimetableItemsQuery($rehearsal->id()->toString(), 2));

        $this->assertSame([], $result);
    }

    public function test_non_member_cannot_list_or_get_items(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->givenProductionWithPrimaryManager(99);

        $created = $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            1,
            '搬入',
            null,
            '2026-10-28T09:00:00+09:00',
            null,
            null,
            null,
            null,
            null,
            [],
            null
        ));

        $this->expectException(TimetableItemAccessDeniedException::class);
        $this->listItems->execute(new ListTimetableItemsQuery($rehearsal->id()->toString(), 99));
    }

    public function test_manager_can_update_item(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);

        $created = $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            1,
            '搬入',
            null,
            '2026-10-28T09:00:00+09:00',
            null,
            null,
            null,
            null,
            null,
            [],
            null
        ));

        $updated = $this->updateItem->execute(new UpdateTimetableItemCommand(
            $created->id,
            1,
            '建て込み',
            '大道具設営',
            '2026-10-28T10:00:00+09:00',
            '2026-10-28T15:00:00+09:00',
            2,
            'SETUP',
            '舞台',
            null,
            [],
            null
        ));

        $this->assertSame('建て込み', $updated->title);
        $this->assertSame('SETUP', $updated->category);
    }

    public function test_plain_member_cannot_update_or_delete_item(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);
        $this->addActivePersonParticipant($production, 2);

        $created = $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            1,
            '搬入',
            null,
            '2026-10-28T09:00:00+09:00',
            null,
            null,
            null,
            null,
            null,
            [],
            null
        ));

        try {
            $this->updateItem->execute(new UpdateTimetableItemCommand(
                $created->id,
                2,
                '改ざん',
                null,
                '2026-10-28T09:00:00+09:00',
                null,
                null,
                null,
                null,
                null,
                [],
                null
            ));
            $this->fail('Expected TimetableItemAccessDeniedException on update.');
        } catch (TimetableItemAccessDeniedException $exception) {
            // expected
        }

        $this->expectException(TimetableItemAccessDeniedException::class);
        $this->deleteItem->execute(new DeleteTimetableItemCommand($created->id, 2));
    }

    public function test_manager_can_delete_item(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);

        $created = $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            1,
            '搬出',
            null,
            '2026-11-02T20:00:00+09:00',
            null,
            null,
            null,
            null,
            null,
            [],
            null
        ));

        $this->deleteItem->execute(new DeleteTimetableItemCommand($created->id, 1));

        $this->expectException(TimetableItemNotFoundException::class);
        $this->getItem->execute(new GetTimetableItemQuery($created->id, 1));
    }

    public function test_overlapping_items_from_different_roles_coexist(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $rehearsal = $this->givenRehearsal($production);

        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            1,
            '音響バランスチェック',
            null,
            '2026-10-29T10:00:00+09:00',
            '2026-10-29T11:00:00+09:00',
            null,
            'TECHNICAL',
            '舞台',
            ParticipantType::STAFF,
            [],
            null
        ));

        // Same time slot, same venue, different activity - must not be
        // rejected as a conflict (Phase 3 instruction §10).
        $result = $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsal->id()->toString(),
            1,
            '照明調整',
            null,
            '2026-10-29T10:00:00+09:00',
            '2026-10-29T11:00:00+09:00',
            null,
            'TECHNICAL',
            '舞台',
            ParticipantType::STAFF,
            [],
            null
        ));

        $this->assertSame('照明調整', $result->title);

        $this->publishDraft($rehearsal->id(), 1);

        $items = $this->listItems->execute(new ListTimetableItemsQuery($rehearsal->id()->toString(), 1));
        $this->assertCount(2, $items);
    }
}
