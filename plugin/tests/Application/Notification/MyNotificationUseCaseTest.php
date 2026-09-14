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
