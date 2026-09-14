<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Notification;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\InAppNotificationAdapter;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;
use StageArt\Tests\Support\InMemoryNotificationRepository;

final class InAppNotificationAdapterTest extends TestCase
{
    public function test_delivers_persists_a_notification_row_with_the_payload_message(): void
    {
        $notifications = new InMemoryNotificationRepository();
        $adapter = new InAppNotificationAdapter($notifications);
        $personId = PersonId::generate();
        $productionId = ProductionId::generate();

        $adapter->deliver($personId, 'rehearsal_cancelled', [
            'message' => 'Show の稽古は中止となりました',
            'production_id' => $productionId->toString(),
        ]);

        $rows = $notifications->findByPersonId($personId);
        $this->assertCount(1, $rows);
        $this->assertSame('rehearsal_cancelled', $rows[0]->type());
        $this->assertSame('Show の稽古は中止となりました', $rows[0]->message());
        $this->assertTrue($rows[0]->productionId()->equals($productionId));
        $this->assertFalse($rows[0]->isRead());
    }

    public function test_falls_back_to_a_generic_message_when_the_payload_has_none(): void
    {
        $notifications = new InMemoryNotificationRepository();
        $adapter = new InAppNotificationAdapter($notifications);
        $personId = PersonId::generate();

        $adapter->deliver($personId, 'some_future_type', []);

        $rows = $notifications->findByPersonId($personId);
        $this->assertCount(1, $rows);
        $this->assertNotSame('', $rows[0]->message());
    }

    public function test_production_id_is_null_when_not_present_in_payload(): void
    {
        $notifications = new InMemoryNotificationRepository();
        $adapter = new InAppNotificationAdapter($notifications);
        $personId = PersonId::generate();

        $adapter->deliver($personId, 'rehearsal_response_reminder', ['message' => 'msg']);

        $rows = $notifications->findByPersonId($personId);
        $this->assertNull($rows[0]->productionId());
    }
}
