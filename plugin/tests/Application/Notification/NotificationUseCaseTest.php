<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Notification;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\GetPushPreferenceQuery;
use StageArt\Application\Notification\GetPushPreferenceUseCase;
use StageArt\Application\Notification\ListNotificationsForProductionQuery;
use StageArt\Application\Notification\ListNotificationsForProductionUseCase;
use StageArt\Application\Notification\MarkNotificationReadCommand;
use StageArt\Application\Notification\MarkNotificationReadUseCase;
use StageArt\Application\Notification\NotificationAccessDeniedException;
use StageArt\Application\Notification\NotificationNotFoundException;
use StageArt\Application\Notification\UpdatePushPreferenceCommand;
use StageArt\Application\Notification\UpdatePushPreferenceUseCase;
use StageArt\Application\Organization\OrganizationAuthorizationService;
use StageArt\Application\Production\ProductionAuthorizationService;
use StageArt\Domain\Membership\Membership;
use StageArt\Domain\Notification\TimetableVersionPublishedNotification;
use StageArt\Domain\Organization\Organization;
use StageArt\Domain\Organization\OrganizationName;
use StageArt\Domain\Participant\Participant;
use StageArt\Domain\Participant\ParticipantSubjectType;
use StageArt\Domain\Participant\ParticipantType;
use StageArt\Domain\Person\Person;
use StageArt\Domain\Production\Production;
use StageArt\Domain\Production\ProductionName;
use StageArt\Domain\Project\Project;
use StageArt\Domain\Rehearsal\RehearsalId;
use StageArt\Domain\Timetable\TimetableId;
use StageArt\Tests\Support\InMemoryMembershipRepository;
use StageArt\Tests\Support\InMemoryNotificationReadStateRepository;
use StageArt\Tests\Support\InMemoryOrganizationRepository;
use StageArt\Tests\Support\InMemoryParticipantRepository;
use StageArt\Tests\Support\InMemoryPersonRepository;
use StageArt\Tests\Support\InMemoryProductionDelegateRepository;
use StageArt\Tests\Support\InMemoryProductionRepository;
use StageArt\Tests\Support\InMemoryPushPreferenceRepository;
use StageArt\Tests\Support\InMemoryTimetableVersionPublishedNotificationRepository;

final class NotificationUseCaseTest extends TestCase
{
    private InMemoryOrganizationRepository $organizations;
    private InMemoryPersonRepository $people;
    private InMemoryMembershipRepository $memberships;
    private InMemoryProductionRepository $productions;
    private InMemoryProductionDelegateRepository $delegates;
    private InMemoryParticipantRepository $participants;
    private InMemoryTimetableVersionPublishedNotificationRepository $notifications;
    private InMemoryNotificationReadStateRepository $readStates;
    private InMemoryPushPreferenceRepository $pushPreferences;

    private ListNotificationsForProductionUseCase $listNotifications;
    private MarkNotificationReadUseCase $markRead;
    private GetPushPreferenceUseCase $getPushPreference;
    private UpdatePushPreferenceUseCase $updatePushPreference;

    protected function setUp(): void
    {
        $this->organizations = new InMemoryOrganizationRepository();
        $this->people = new InMemoryPersonRepository();
        $this->memberships = new InMemoryMembershipRepository();
        $this->productions = new InMemoryProductionRepository();
        $this->delegates = new InMemoryProductionDelegateRepository();
        $this->participants = new InMemoryParticipantRepository();
        $this->notifications = new InMemoryTimetableVersionPublishedNotificationRepository();
        $this->readStates = new InMemoryNotificationReadStateRepository();
        $this->pushPreferences = new InMemoryPushPreferenceRepository();

        $organizationAuthorization = new OrganizationAuthorizationService($this->people, $this->memberships);
        $productionAuthorization = new ProductionAuthorizationService(
            $organizationAuthorization,
            $this->delegates,
            $this->participants
        );

        $this->listNotifications = new ListNotificationsForProductionUseCase(
            $this->productions,
            $this->notifications,
            $this->readStates,
            $productionAuthorization
        );
        $this->markRead = new MarkNotificationReadUseCase(
            $this->notifications,
            $this->readStates,
            $this->productions,
            $productionAuthorization
        );
        $this->getPushPreference = new GetPushPreferenceUseCase($this->pushPreferences, $productionAuthorization);
        $this->updatePushPreference = new UpdatePushPreferenceUseCase($this->pushPreferences, $productionAuthorization);
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

    private function seedNotification(Production $production, int $version): TimetableVersionPublishedNotification
    {
        $notification = TimetableVersionPublishedNotification::create(
            $production->id(),
            RehearsalId::generate(),
            TimetableId::generate(),
            $version,
            $production->primaryManagerPersonId(),
            new DateTimeImmutable(),
            'テスト変更概要'
        );
        $this->notifications->save($notification);

        return $notification;
    }

    public function test_production_member_can_list_notifications(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->seedNotification($production, 1);
        $this->seedNotification($production, 2);

        $results = $this->listNotifications->execute(
            new ListNotificationsForProductionQuery($production->id()->toString(), 1)
        );

        $this->assertCount(2, $results);
        $this->assertSame('timetable_version_published', $results[0]->type);
        $this->assertFalse($results[0]->isRead);
    }

    public function test_non_member_cannot_list_notifications(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->seedNotification($production, 1);
        $outsider = Person::create(99);
        $this->people->save($outsider);

        $this->expectException(NotificationAccessDeniedException::class);

        $this->listNotifications->execute(
            new ListNotificationsForProductionQuery($production->id()->toString(), 99)
        );
    }

    /**
     * Phase 4 instruction §16: no Role/Participant Type-based
     * restriction on Notification targets - a CAST member and a STAFF
     * member of the same Production see the identical list.
     */
    public function test_cast_and_staff_participants_see_the_same_notifications(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $this->seedNotification($production, 1);
        $cast = $this->addActivePersonParticipant($production, 2, ParticipantType::cast());
        $staff = $this->addActivePersonParticipant($production, 3, ParticipantType::staff());

        $castResults = $this->listNotifications->execute(
            new ListNotificationsForProductionQuery($production->id()->toString(), 2)
        );
        $staffResults = $this->listNotifications->execute(
            new ListNotificationsForProductionQuery($production->id()->toString(), 3)
        );

        $this->assertCount(1, $castResults);
        $this->assertCount(1, $staffResults);
        $this->assertSame($castResults[0]->id, $staffResults[0]->id);
    }

    public function test_push_preference_defaults_to_enabled_when_never_set(): void
    {
        $this->givenProductionWithPrimaryManager(1);

        $result = $this->getPushPreference->execute(new GetPushPreferenceQuery(1));

        $this->assertTrue($result->enabled);
        $this->assertNull($result->updatedAt);
    }

    public function test_update_push_preference_persists_and_is_reflected_on_get(): void
    {
        $this->givenProductionWithPrimaryManager(1);

        $updateResult = $this->updatePushPreference->execute(new UpdatePushPreferenceCommand(1, false));
        $this->assertFalse($updateResult->enabled);

        $getResult = $this->getPushPreference->execute(new GetPushPreferenceQuery(1));
        $this->assertFalse($getResult->enabled);
        $this->assertNotNull($getResult->updatedAt);
    }

    /**
     * Phase 4 instruction §25 "Push設定: 本人のみ" - since the target
     * Person is always the requester's own WordPress user id, changing
     * one Person's preference can never affect another's.
     */
    public function test_updating_one_persons_preference_does_not_affect_another(): void
    {
        $this->givenProductionWithPrimaryManager(1);
        $second = Person::create(2);
        $this->people->save($second);

        $this->updatePushPreference->execute(new UpdatePushPreferenceCommand(1, false));

        $otherResult = $this->getPushPreference->execute(new GetPushPreferenceQuery(2));
        $this->assertTrue($otherResult->enabled);
    }

    public function test_marking_a_notification_read_is_reflected_only_for_that_person(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $notification = $this->seedNotification($production, 1);
        $other = $this->addActivePersonParticipant($production, 2, ParticipantType::cast());

        $this->markRead->execute(new MarkNotificationReadCommand($notification->id()->toString(), 1));

        $mine = $this->listNotifications->execute(new ListNotificationsForProductionQuery($production->id()->toString(), 1));
        $theirs = $this->listNotifications->execute(new ListNotificationsForProductionQuery($production->id()->toString(), 2));

        $this->assertTrue($mine[0]->isRead);
        $this->assertFalse($theirs[0]->isRead);
    }

    public function test_marking_an_already_read_notification_read_again_is_a_harmless_no_op(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $notification = $this->seedNotification($production, 1);

        $this->markRead->execute(new MarkNotificationReadCommand($notification->id()->toString(), 1));
        $this->markRead->execute(new MarkNotificationReadCommand($notification->id()->toString(), 1));

        $results = $this->listNotifications->execute(new ListNotificationsForProductionQuery($production->id()->toString(), 1));
        $this->assertTrue($results[0]->isRead);
    }

    public function test_non_member_cannot_mark_a_notification_read(): void
    {
        $production = $this->givenProductionWithPrimaryManager(1);
        $notification = $this->seedNotification($production, 1);
        $outsider = Person::create(99);
        $this->people->save($outsider);

        $this->expectException(NotificationAccessDeniedException::class);

        $this->markRead->execute(new MarkNotificationReadCommand($notification->id()->toString(), 99));
    }

    public function test_marking_a_nonexistent_notification_read_throws_not_found(): void
    {
        $this->givenProductionWithPrimaryManager(1);

        $this->expectException(NotificationNotFoundException::class);

        $this->markRead->execute(new MarkNotificationReadCommand('11111111-1111-4111-8111-111111111111', 1));
    }
}
