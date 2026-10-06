<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Notification;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\ListMyNotificationsQuery;
use StageArt\Application\Notification\ListMyNotificationsUseCase;
use StageArt\Application\Notification\MarkMyNotificationReadCommand;
use StageArt\Application\Notification\MarkMyNotificationReadUseCase;
use StageArt\Application\Notification\NotificationAccessDeniedException;
use StageArt\Application\Notification\NotificationNotFoundException;
use StageArt\Domain\Notification\Notification;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Tests\Support\FakeIdentityContract;
use StageArt\Tests\Support\InMemoryNotificationRepository;

final class MyNotificationUseCaseTest extends TestCase
{
    private InMemoryNotificationRepository $notifications;
    private FakeIdentityContract $identity;
    private ListMyNotificationsUseCase $listMine;
    private MarkMyNotificationReadUseCase $markMineRead;

    protected function setUp(): void
    {
        $this->notifications = new InMemoryNotificationRepository();
        $this->identity = new FakeIdentityContract();
        $this->listMine = new ListMyNotificationsUseCase($this->notifications, $this->identity);
        $this->markMineRead = new MarkMyNotificationReadUseCase($this->notifications, $this->identity);
    }

    public function test_lists_only_the_callers_own_notifications(): void
    {
        $me = PersonId::generate();
        $someoneElse = PersonId::generate();
        $this->identity->register(1, $me);

        $this->notifications->save(Notification::create($me, 'rehearsal_cancelled', 'mine', null));
        $this->notifications->save(Notification::create($someoneElse, 'rehearsal_cancelled', 'not mine', null));

        $results = $this->listMine->execute(new ListMyNotificationsQuery(1));

        $this->assertCount(1, $results);
        $this->assertSame('mine', $results[0]->message);
    }

    /**
     * StageArt ホームお知らせ・稽古出欠通知 instruction: `rehearsal_id` must
     * reach the API response (`GET /me/notifications`), not just the
     * Domain Entity - the Frontend has nothing else to navigate to the
     * right Rehearsal with.
     */
    public function test_rehearsal_id_is_included_in_the_result(): void
    {
        $me = PersonId::generate();
        $this->identity->register(1, $me);
        $this->notifications->save(
            Notification::create($me, 'rehearsal_response_reminder', 'mine', ProductionId::generate(), 'rehearsal-42')
        );

        $results = $this->listMine->execute(new ListMyNotificationsQuery(1));

        $this->assertSame('rehearsal-42', $results[0]->rehearsalId);
    }

    public function test_rehearsal_id_is_null_for_a_notification_not_about_a_specific_rehearsal(): void
    {
        $me = PersonId::generate();
        $this->identity->register(1, $me);
        $this->notifications->save(Notification::create($me, 'timetable_version_published', 'mine', ProductionId::generate()));

        $results = $this->listMine->execute(new ListMyNotificationsQuery(1));

        $this->assertNull($results[0]->rehearsalId);
    }

    public function test_list_rejects_a_wordpress_user_with_no_linked_person(): void
    {
        $this->expectException(NotificationAccessDeniedException::class);
        $this->listMine->execute(new ListMyNotificationsQuery(99));
    }

    public function test_mark_read_sets_is_read(): void
    {
        $me = PersonId::generate();
        $this->identity->register(1, $me);
        $notification = Notification::create($me, 'rehearsal_cancelled', 'mine', null);
        $this->notifications->save($notification);

        $this->markMineRead->execute(new MarkMyNotificationReadCommand($notification->id()->toString(), 1));

        $results = $this->listMine->execute(new ListMyNotificationsQuery(1));
        $this->assertTrue($results[0]->isRead);
    }

    public function test_mark_read_rejects_marking_someone_elses_notification(): void
    {
        $me = PersonId::generate();
        $someoneElse = PersonId::generate();
        $this->identity->register(1, $me);
        $this->identity->register(2, $someoneElse);
        $notification = Notification::create($someoneElse, 'rehearsal_cancelled', 'not mine', null);
        $this->notifications->save($notification);

        $this->expectException(NotificationAccessDeniedException::class);
        $this->markMineRead->execute(new MarkMyNotificationReadCommand($notification->id()->toString(), 1));
    }

    public function test_mark_read_on_an_unknown_id_is_not_found(): void
    {
        $me = PersonId::generate();
        $this->identity->register(1, $me);

        $this->expectException(NotificationNotFoundException::class);
        $this->markMineRead->execute(new MarkMyNotificationReadCommand(\StageArt\Domain\Notification\NotificationId::generate()->toString(), 1));
    }
}
