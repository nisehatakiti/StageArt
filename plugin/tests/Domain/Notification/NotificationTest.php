<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Notification;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Notification\Notification;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

final class NotificationTest extends TestCase
{
    public function test_new_notification_is_unread(): void
    {
        $notification = Notification::create(PersonId::generate(), 'rehearsal_cancelled', 'Show の中止通知', ProductionId::generate());

        $this->assertFalse($notification->isRead());
        $this->assertNull($notification->readAt());
    }

    public function test_mark_read_sets_read_at(): void
    {
        $notification = Notification::create(PersonId::generate(), 'rehearsal_cancelled', 'message', null);

        $notification->markRead();

        $this->assertTrue($notification->isRead());
        $this->assertNotNull($notification->readAt());
    }

    public function test_mark_read_is_idempotent_and_preserves_the_original_read_at(): void
    {
        $notification = Notification::create(PersonId::generate(), 'rehearsal_cancelled', 'message', null);

        $notification->markRead();
        $firstReadAt = $notification->readAt();

        usleep(1000);
        $notification->markRead();

        $this->assertSame($firstReadAt, $notification->readAt(), 'A second markRead() must not overwrite the original readAt.');
    }

    public function test_production_id_is_optional(): void
    {
        $notification = Notification::create(PersonId::generate(), 'rehearsal_cancelled', 'message', null);

        $this->assertNull($notification->productionId());
    }
}
