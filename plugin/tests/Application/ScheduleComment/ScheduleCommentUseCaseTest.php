<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\ScheduleComment;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Application\Production\ProductionOrganizationResolver;
use StageArt\Application\Rehearsal\CreateRehearsalCommand;
use StageArt\Application\Rehearsal\CreateRehearsalUseCase;
use StageArt\Core\Adapter\CoreAuthorizationAdapter;
use StageArt\Core\Adapter\CoreIdentityAdapter;
use StageArt\Core\Adapter\CoreMembershipAdapter;
use StageArt\Core\Adapter\CoreProductionContextAdapter;
use StageArt\Application\ScheduleComment\CreateScheduleCommentCommand;
use StageArt\Application\ScheduleComment\CreateScheduleCommentUseCase;
use StageArt\Application\ScheduleComment\CreateTimetableItemScheduleCommentCommand;
use StageArt\Application\ScheduleComment\CreateTimetableItemScheduleCommentUseCase;
use StageArt\Application\ScheduleComment\DeleteScheduleCommentCommand;
use StageArt\Application\ScheduleComment\DeleteScheduleCommentUseCase;
use StageArt\Application\ScheduleComment\ListScheduleCommentsForRehearsalQuery;
use StageArt\Application\ScheduleComment\ListScheduleCommentsUseCase;
use StageArt\Application\ScheduleComment\ListTimetableItemScheduleCommentsQuery;
use StageArt\Application\ScheduleComment\ListTimetableItemScheduleCommentsUseCase;
use StageArt\Application\ScheduleComment\ScheduleCommentAccessDeniedException;
use StageArt\Application\ScheduleComment\UpdateScheduleCommentCommand;
use StageArt\Application\ScheduleComment\UpdateScheduleCommentUseCase;
use StageArt\Application\Timetable\NextTimetableVersionResolver;
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
use StageArt\Domain\ProductionDelegate\ProductionDelegate;
use StageArt\Domain\Role\RoleKey;
use StageArt\Domain\Project\Project;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryProjectRepository;
use StageArt\Tests\Support\InMemoryRehearsalAttendanceRepository;
use StageArt\Tests\Support\InMemoryRehearsalRepository;
use StageArt\Tests\Support\InMemoryScheduleCommentRepository;
use StageArt\Tests\Support\InMemoryTimetableItemRepository;
use StageArt\Tests\Support\InMemoryTimetableRepository;
use StageArt\Tests\Support\InMemoryTransactionManager;

final class ScheduleCommentUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private InMemoryRehearsalRepository $rehearsals;
    private InMemoryTimetableRepository $timetables;
    private InMemoryTimetableItemRepository $timetableItems;
    private InMemoryScheduleCommentRepository $comments;

    private CreateRehearsalUseCase $createRehearsal;
    private CreateTimetableItemUseCase $createTimetableItem;
    private CreateScheduleCommentUseCase $createComment;
    private ListScheduleCommentsUseCase $listComments;
    private UpdateScheduleCommentUseCase $updateComment;
    private DeleteScheduleCommentUseCase $deleteComment;
    private CreateTimetableItemScheduleCommentUseCase $createTimetableItemComment;
    private ListTimetableItemScheduleCommentsUseCase $listTimetableItemComments;

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
        $this->timetableItems = new InMemoryTimetableItemRepository();
        $this->comments = new InMemoryScheduleCommentRepository();

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

        $this->createRehearsal = new CreateRehearsalUseCase(
            $productionContext,
            $this->rehearsals,
            new InMemoryRehearsalAttendanceRepository(),
            $memberResolver,
            $identity,
            $authorization,
            $transactions
        );

        $this->createTimetableItem = new CreateTimetableItemUseCase(
            $this->rehearsals,
            $productionContext,
            $this->timetables,
            $this->timetableItems,
            new TimetableItemTargetValidator($memberResolver),
            new NextTimetableVersionResolver($this->timetables),
            $identity,
            $authorization,
            $transactions
        );

        $this->createComment = new CreateScheduleCommentUseCase(
            $this->comments,
            $this->rehearsals,
            $productionContext,
            $identity,
            $memberResolver
        );
        $this->listComments = new ListScheduleCommentsUseCase(
            $this->comments,
            $this->rehearsals,
            $productionContext,
            $identity,
            $memberResolver
        );
        $this->updateComment = new UpdateScheduleCommentUseCase($this->comments, $identity);
        $this->deleteComment = new DeleteScheduleCommentUseCase(
            $this->comments,
            $this->rehearsals,
            $productionContext,
            $this->timetables,
            $this->timetableItems,
            $identity,
            $authorization
        );
        $this->createTimetableItemComment = new CreateTimetableItemScheduleCommentUseCase(
            $this->comments,
            $this->timetableItems,
            $this->timetables,
            $this->rehearsals,
            $productionContext,
            $identity,
            $memberResolver
        );
        $this->listTimetableItemComments = new ListTimetableItemScheduleCommentsUseCase(
            $this->comments,
            $this->timetableItems,
            $this->timetables,
            $this->rehearsals,
            $productionContext,
            $identity,
            $memberResolver
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

    private function givenTimetableItem(string $rehearsalId, int $creatorWordPressUserId, string $title): string
    {
        $item = $this->createTimetableItem->execute(new CreateTimetableItemCommand(
            $rehearsalId,
            $creatorWordPressUserId,
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
        ));

        return $item->id;
    }

    public function test_any_production_member_can_post_and_all_members_can_read_it(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);
        $this->addActivePersonParticipant($production, 3);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null
        ));

        // Posted by a plain member (2), not the manager - visible to another
        // plain member (3) who is not necessarily a target of this Rehearsal's
        // Attendance, per ScheduleComment.md's "Visibility": all Production
        // members, not only the Rehearsal's Attendance targets.
        $posted = $this->createComment->execute(new CreateScheduleCommentCommand($rehearsal->id, 2, 'Can we move the start time?'));

        $visibleToThirdMember = $this->listComments->execute(new ListScheduleCommentsForRehearsalQuery($rehearsal->id, 3));

        $this->assertCount(1, $visibleToThirdMember);
        $this->assertSame($posted->id, $visibleToThirdMember[0]->id);
    }

    public function test_non_member_cannot_post_or_read(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->givenProductionWithPrimaryManager(99);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null
        ));

        $this->expectException(ScheduleCommentAccessDeniedException::class);
        $this->createComment->execute(new CreateScheduleCommentCommand($rehearsal->id, 99, 'I should not be able to post this.'));
    }

    public function test_only_author_can_edit(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null
        ));

        $posted = $this->createComment->execute(new CreateScheduleCommentCommand($rehearsal->id, 2, 'Original text'));

        $edited = $this->updateComment->execute(new UpdateScheduleCommentCommand($posted->id, 2, 'Edited text'));
        $this->assertSame('Edited text', $edited->body);

        $this->expectException(ScheduleCommentAccessDeniedException::class);
        $this->updateComment->execute(new UpdateScheduleCommentCommand($posted->id, 1, 'Manager tries to edit'));
    }

    public function test_author_can_delete_own_comment(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null
        ));

        $posted = $this->createComment->execute(new CreateScheduleCommentCommand($rehearsal->id, 2, 'Delete me'));

        $this->deleteComment->execute(new DeleteScheduleCommentCommand($posted->id, 2));

        $remaining = $this->listComments->execute(new ListScheduleCommentsForRehearsalQuery($rehearsal->id, 1));
        $this->assertCount(0, $remaining);
    }

    public function test_manager_can_delete_any_comment_but_plain_member_cannot(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);
        $this->addActivePersonParticipant($production, 3);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null
        ));

        $posted = $this->createComment->execute(new CreateScheduleCommentCommand($rehearsal->id, 2, 'Posted by member 2'));

        // A different plain member (3) may not delete someone else's comment.
        try {
            $this->deleteComment->execute(new DeleteScheduleCommentCommand($posted->id, 3));
            $this->fail('Expected ScheduleCommentAccessDeniedException.');
        } catch (ScheduleCommentAccessDeniedException $exception) {
            // expected
        }

        // The PrimaryManager may delete it.
        $this->deleteComment->execute(new DeleteScheduleCommentCommand($posted->id, 1));

        $remaining = $this->listComments->execute(new ListScheduleCommentsForRehearsalQuery($rehearsal->id, 1));
        $this->assertCount(0, $remaining);
    }

    public function test_rehearsal_manager_delegate_can_delete_any_comment(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $delegatePerson = Person::create(4);
        $this->people->save($delegatePerson);
        $this->delegates->save(ProductionDelegate::create(
            $production->id(),
            $delegatePerson->id(),
            RoleKey::rehearsalManager(),
            $production->primaryManagerPersonId()
        ));

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null
        ));

        $posted = $this->createComment->execute(new CreateScheduleCommentCommand($rehearsal->id, 2, 'Posted by member 2'));

        $this->deleteComment->execute(new DeleteScheduleCommentCommand($posted->id, 4));

        $remaining = $this->listComments->execute(new ListScheduleCommentsForRehearsalQuery($rehearsal->id, 1));
        $this->assertCount(0, $remaining);
    }

    public function test_any_production_member_can_post_on_timetable_item_and_all_members_can_read_it(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);
        $this->addActivePersonParticipant($production, 3);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null
        ));

        $itemId = $this->givenTimetableItem($rehearsal->id, 1, '照明シュート');

        $posted = $this->createTimetableItemComment->execute(
            new CreateTimetableItemScheduleCommentCommand($itemId, 2, '15分前集合でお願いします')
        );

        $visibleToThirdMember = $this->listTimetableItemComments->execute(
            new ListTimetableItemScheduleCommentsQuery($itemId, 3)
        );

        $this->assertCount(1, $visibleToThirdMember);
        $this->assertSame($posted->id, $visibleToThirdMember[0]->id);
        $this->assertSame($itemId, $visibleToThirdMember[0]->timetableItemId);
        $this->assertNull($visibleToThirdMember[0]->rehearsalId);
    }

    public function test_non_member_cannot_post_or_read_timetable_item_comment(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->givenProductionWithPrimaryManager(99);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null
        ));

        $itemId = $this->givenTimetableItem($rehearsal->id, 1, '照明シュート');

        $this->expectException(ScheduleCommentAccessDeniedException::class);
        $this->createTimetableItemComment->execute(
            new CreateTimetableItemScheduleCommentCommand($itemId, 99, 'I should not be able to post this.')
        );
    }

    /**
     * The exact scenario the review flagged: a Rehearsal-comment and a
     * TimetableItem-comment on the same underlying Rehearsal must never
     * leak into each other's list.
     */
    public function test_rehearsal_comments_and_timetable_item_comments_do_not_cross_contaminate(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null
        ));

        $itemId = $this->givenTimetableItem($rehearsal->id, 1, '照明シュート');

        $this->createComment->execute(new CreateScheduleCommentCommand($rehearsal->id, 2, 'Rehearsal comment'));
        $this->createTimetableItemComment->execute(
            new CreateTimetableItemScheduleCommentCommand($itemId, 2, 'TimetableItem comment')
        );

        $rehearsalComments = $this->listComments->execute(new ListScheduleCommentsForRehearsalQuery($rehearsal->id, 1));
        $itemComments = $this->listTimetableItemComments->execute(new ListTimetableItemScheduleCommentsQuery($itemId, 1));

        $this->assertCount(1, $rehearsalComments);
        $this->assertSame('Rehearsal comment', $rehearsalComments[0]->body);

        $this->assertCount(1, $itemComments);
        $this->assertSame('TimetableItem comment', $itemComments[0]->body);
    }

    public function test_author_can_delete_own_timetable_item_comment(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null
        ));

        $itemId = $this->givenTimetableItem($rehearsal->id, 1, '照明シュート');

        $posted = $this->createTimetableItemComment->execute(
            new CreateTimetableItemScheduleCommentCommand($itemId, 2, 'Delete me')
        );

        $this->deleteComment->execute(new DeleteScheduleCommentCommand($posted->id, 2));

        $remaining = $this->listTimetableItemComments->execute(new ListTimetableItemScheduleCommentsQuery($itemId, 1));
        $this->assertCount(0, $remaining);
    }

    public function test_manager_can_delete_any_timetable_item_comment_but_plain_member_cannot(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->addActivePersonParticipant($production, 2);
        $this->addActivePersonParticipant($production, 3);

        $rehearsal = $this->createRehearsal->execute(new CreateRehearsalCommand(
            $production->id()->toString(),
            1,
            'Act 1',
            null,
            null,
            null,
            null,
            null
        ));

        $itemId = $this->givenTimetableItem($rehearsal->id, 1, '照明シュート');

        $posted = $this->createTimetableItemComment->execute(
            new CreateTimetableItemScheduleCommentCommand($itemId, 2, 'Posted by member 2')
        );

        try {
            $this->deleteComment->execute(new DeleteScheduleCommentCommand($posted->id, 3));
            $this->fail('Expected ScheduleCommentAccessDeniedException.');
        } catch (ScheduleCommentAccessDeniedException $exception) {
            // expected
        }

        $this->deleteComment->execute(new DeleteScheduleCommentCommand($posted->id, 1));

        $remaining = $this->listTimetableItemComments->execute(new ListTimetableItemScheduleCommentsQuery($itemId, 1));
        $this->assertCount(0, $remaining);
    }
}
