<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ProductionSchedule;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\ProductionSchedule\ListProductionTimetableItemsQuery;
use StageArt\Application\ProductionSchedule\ListProductionTimetableItemsUseCase;
use StageArt\Application\ProductionSchedule\ProductionScheduleAccessDeniedException;
use StageArt\Application\Timetable\NextTimetableVersionResolver;
use StageArt\Application\Timetable\PublishTimetableVersionCommand;
use StageArt\Application\Timetable\PublishTimetableVersionUseCase;
use StageArt\Application\TimetableItem\CreateTimetableItemCommand;
use StageArt\Application\TimetableItem\CreateTimetableItemUseCase;
use StageArt\Application\TimetableItem\TimetableItemTargetValidator;
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
use StageArt\Domain\Rehearsal\RehearsalId;
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

/**
 * The Phase 3 review's central requirement: Timetable stays parented to
 * Rehearsal (see Timetable::class), but Production Members must still be
 * able to view a single, chronologically-ordered schedule spanning every
 * Rehearsal in the Production - realized here as a pure read-side
 * aggregation, never a change to Timetable's own Cardinality.
 */
final class ListProductionTimetableItemsUseCaseTest extends TestCase
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
    private ListProductionTimetableItemsUseCase $listProductionItems;

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
        $transactions = new InMemoryTransactionManager();
        $versionResolver = new NextTimetableVersionResolver($this->timetables);

        $this->createItem = new CreateTimetableItemUseCase(
            $this->rehearsals,
            $this->productions,
            $this->timetables,
            $this->items,
            new TimetableItemTargetValidator($this->participants),
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

        $this->listProductionItems = new ListProductionTimetableItemsUseCase(
            $this->productions,
            $this->rehearsals,
            $this->timetables,
            $this->items,
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

        $this->participants->save(Participant::create(
            $production->id(),
            ParticipantSubjectType::person(),
            $person->id()->toString(),
            ParticipantType::fromString($participantType)
        ));

        return $person;
    }

    private function givenRehearsal(Production $production, string $title): Rehearsal
    {
        $rehearsal = Rehearsal::create($production->id(), $title, null, null, null, null, null);
        $this->rehearsals->save($rehearsal);

        return $rehearsal;
    }

    /**
     * The Production Schedule aggregates each Rehearsal's PUBLISHED
     * Version only - tests populate a Rehearsal's DRAFT then publish it
     * before expecting the aggregate to include it.
     */
    private function publishDraft(RehearsalId $rehearsalId, int $publisherWordPressUserId): void
    {
        $draft = $this->timetables->findDraftByRehearsalId($rehearsalId);
        $this->publishVersion->execute(new PublishTimetableVersionCommand($draft->id()->toString(), $publisherWordPressUserId, null));
    }

    public function test_aggregates_items_across_multiple_rehearsals_in_chronological_order(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $rehearsalA = $this->givenRehearsal($production, '搬入・建て込み');
        $rehearsalB = $this->givenRehearsal($production, '場当たり・ゲネ');
        $rehearsalC = $this->givenRehearsal($production, '本番');

        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsalC->id()->toString(), 1, '開演', null, '2026-10-31T13:00:00+09:00', null, null, null, null, null, [], null
        ));
        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsalA->id()->toString(), 1, '搬入', null, '2026-10-28T09:00:00+09:00', null, null, null, null, null, [], null
        ));
        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsalB->id()->toString(), 1, '場当たり', null, '2026-10-30T10:00:00+09:00', null, null, null, null, null, [], null
        ));

        $this->publishDraft($rehearsalA->id(), 1);
        $this->publishDraft($rehearsalB->id(), 1);
        $this->publishDraft($rehearsalC->id(), 1);

        $schedule = $this->listProductionItems->execute(new ListProductionTimetableItemsQuery($production->id()->toString(), 1));

        $this->assertCount(3, $schedule);
        $this->assertSame(['搬入', '場当たり', '開演'], array_map(static fn ($item) => $item->title, $schedule));
    }

    public function test_all_production_members_regardless_of_participant_type_see_the_same_aggregate_set(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $castMember = $this->addActivePersonParticipant($production, 2, ParticipantType::CAST);
        $staffMember = $this->addActivePersonParticipant($production, 3, ParticipantType::STAFF);

        $rehearsalA = $this->givenRehearsal($production, '仕込み');
        $rehearsalB = $this->givenRehearsal($production, '本番');

        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsalA->id()->toString(), 1, '照明シュート', null, '2026-10-28T10:00:00+09:00', null, null, 'TECHNICAL', '舞台', ParticipantType::STAFF, [$staffMember->id()->toString()], null
        ));
        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsalB->id()->toString(), 1, '開演', null, '2026-10-31T13:00:00+09:00', null, null, null, null, ParticipantType::CAST, [$castMember->id()->toString()], null
        ));

        $this->publishDraft($rehearsalA->id(), 1);
        $this->publishDraft($rehearsalB->id(), 1);

        $asManager = $this->listProductionItems->execute(new ListProductionTimetableItemsQuery($production->id()->toString(), 1));
        $asCast = $this->listProductionItems->execute(new ListProductionTimetableItemsQuery($production->id()->toString(), 2));
        $asStaff = $this->listProductionItems->execute(new ListProductionTimetableItemsQuery($production->id()->toString(), 3));

        $this->assertCount(2, $asManager);
        $this->assertCount(2, $asCast, 'CAST member must also see the STAFF-only item across Rehearsals.');
        $this->assertCount(2, $asStaff, 'STAFF member must also see the CAST-only item across Rehearsals.');
    }

    public function test_non_member_cannot_access_production_schedule(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->givenProductionWithPrimaryManager(99);
        $this->givenRehearsal($production, '搬入');

        $this->expectException(ProductionScheduleAccessDeniedException::class);
        $this->listProductionItems->execute(new ListProductionTimetableItemsQuery($production->id()->toString(), 99));
    }

    public function test_other_productions_items_are_excluded(): void
    {
        $productionA = $this->givenProductionWithPrimaryManager(1);
        $productionB = $this->givenProductionWithPrimaryManager(2);

        $rehearsalA = $this->givenRehearsal($productionA, 'A Rehearsal');
        $rehearsalB = $this->givenRehearsal($productionB, 'B Rehearsal');

        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsalA->id()->toString(), 1, 'A Item', null, '2026-10-28T10:00:00+09:00', null, null, null, null, null, [], null
        ));
        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsalB->id()->toString(), 2, 'B Item', null, '2026-10-28T10:00:00+09:00', null, null, null, null, null, [], null
        ));

        $this->publishDraft($rehearsalA->id(), 1);
        $this->publishDraft($rehearsalB->id(), 2);

        $scheduleForA = $this->listProductionItems->execute(new ListProductionTimetableItemsQuery($productionA->id()->toString(), 1));

        $this->assertCount(1, $scheduleForA);
        $this->assertSame('A Item', $scheduleForA[0]->title);
    }

    /**
     * Phase 3.5 instruction §18/§24: the full Production period is the
     * default (no range given); an optional [from, to] window narrows
     * results without needing the UseCase to know about any specific
     * client's "today + tomorrow" default.
     */
    public function test_optional_date_range_narrows_the_aggregate(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);

        $rehearsalA = $this->givenRehearsal($production, '仕込み');
        $rehearsalB = $this->givenRehearsal($production, '本番');

        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsalA->id()->toString(), 1, '搬入', null, '2026-10-28T09:00:00+09:00', null, null, null, null, null, [], null
        ));
        $this->createItem->execute(new CreateTimetableItemCommand(
            $rehearsalB->id()->toString(), 1, '開演', null, '2026-10-31T13:00:00+09:00', null, null, null, null, null, [], null
        ));

        $this->publishDraft($rehearsalA->id(), 1);
        $this->publishDraft($rehearsalB->id(), 1);

        $fullPeriod = $this->listProductionItems->execute(new ListProductionTimetableItemsQuery($production->id()->toString(), 1));
        $this->assertCount(2, $fullPeriod, 'With no range given, the full Production period is returned.');

        $narrowed = $this->listProductionItems->execute(new ListProductionTimetableItemsQuery(
            $production->id()->toString(),
            1,
            '2026-10-31T00:00:00+09:00',
            '2026-11-01T23:59:59+09:00'
        ));
        $this->assertCount(1, $narrowed);
        $this->assertSame('開演', $narrowed[0]->title);
    }
}
