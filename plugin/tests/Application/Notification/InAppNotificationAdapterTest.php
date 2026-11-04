<?php

declare(strict_types=1);

namespace StageArt\Tests\Application\Notification;

use PHPUnit\Framework\TestCase;
use StageArt\Application\Notification\InAppNotificationAdapter;
use StageArt\Application\Notification\MyNotificationResult;
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

    /**
     * StageArt ホームお知らせ・稽古出欠通知 instruction: the Frontend must be
     * able to navigate straight to the right Rehearsal (a Production can
     * have several) without parsing the message string - this is the
     * exact point where `rehearsal_id` already arrives in the payload
     * (RehearsalReminderDispatcher/CancelRehearsalUseCase both already
     * send it) and must now actually be persisted, not silently dropped.
     */
    public function test_persists_rehearsal_id_when_present_in_payload(): void
    {
        $notifications = new InMemoryNotificationRepository();
        $adapter = new InAppNotificationAdapter($notifications);
        $personId = PersonId::generate();
        $productionId = ProductionId::generate();

        $adapter->deliver($personId, 'rehearsal_response_reminder', [
            'message' => 'Show の2026/09/20の稽古の出欠を回答してください',
            'production_id' => $productionId->toString(),
            'rehearsal_id' => 'rehearsal-abc-123',
        ]);

        $rows = $notifications->findByPersonId($personId);
        $this->assertSame('rehearsal-abc-123', $rows[0]->rehearsalId());
    }

    public function test_rehearsal_id_is_null_when_not_present_in_payload(): void
    {
        $notifications = new InMemoryNotificationRepository();
        $adapter = new InAppNotificationAdapter($notifications);
        $personId = PersonId::generate();

        $adapter->deliver($personId, 'timetable_version_published', ['message' => 'msg']);

        $rows = $notifications->findByPersonId($personId);
        $this->assertNull($rows[0]->rehearsalId());
    }

    /**
     * 修正8: a Module's generic deep-link target (an Expense awaiting
     * approval) is stored and returned as-is, so the app opens it
     * directly.
     */
    public function test_persists_a_generic_target_and_returns_it(): void
    {
        $notifications = new InMemoryNotificationRepository();
        $adapter = new InAppNotificationAdapter($notifications);
        $personId = PersonId::generate();

        $adapter->deliver($personId, 'expense_submitted', [
            'message' => '経費申請が提出されました。',
            'production_id' => ProductionId::generate()->toString(),
            'target_type' => 'expense',
            'target_id' => 'expense-123',
        ]);

        $row = $notifications->findByPersonId($personId)[0];
        $this->assertSame('expense', $row->targetType());
        $this->assertSame('expense-123', $row->targetId());

        $result = MyNotificationResult::fromDomain($row)->toArray();
        $this->assertSame('expense', $result['target_type']);
        $this->assertSame('expense-123', $result['target_id']);
        $this->assertNull($result['rehearsal_id']);
    }

    public function test_a_half_given_target_is_dropped(): void
    {
        $notifications = new InMemoryNotificationRepository();
        $adapter = new InAppNotificationAdapter($notifications);
        $personId = PersonId::generate();

        $adapter->deliver($personId, 'expense_submitted', ['message' => 'msg', 'target_type' => 'expense']);

        $row = $notifications->findByPersonId($personId)[0];
        $this->assertNull($row->targetType());
        $this->assertNull($row->targetId());
    }
}
