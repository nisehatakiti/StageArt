<?php

declare(strict_types=1);

namespace StageArt\Tests\Domain\Notification;

use PHPUnit\Framework\TestCase;
use StageArt\Domain\Notification\NotificationReadState;
use StageArt\Domain\Person\PersonId;

final class NotificationReadStateTest extends TestCase
{
    public function test_create_sets_person_and_notification_and_a_read_at_timestamp(): void
    {
        $personId = PersonId::generate();

        $readState = NotificationReadState::create($personId, 'some-notification-id');

        $this->assertTrue($readState->personId()->equals($personId));
        $this->assertSame('some-notification-id', $readState->notificationId());
        $this->assertLessThanOrEqual(new \DateTimeImmutable(), $readState->readAt());
    }
}
