<?php

declare(strict_types=1);

namespace StageArt\Tests\Core\Adapter;

use PHPUnit\Framework\TestCase;
use StageArt\Core\Adapter\CoreNotificationAdapter;
use StageArt\Domain\Person\PersonId;
use StageArt\Tests\Support\InMemoryNotificationDispatcher;

final class CoreNotificationAdapterTest extends TestCase
{
    public function test_notify_delegates_to_the_dispatcher(): void
    {
        $dispatcher = new InMemoryNotificationDispatcher();
        $adapter = new CoreNotificationAdapter($dispatcher);
        $personId = PersonId::generate();

        $adapter->notify($personId, 'timetable_version_published', ['rehearsal_id' => 'abc']);

        $dispatched = $dispatcher->dispatched();
        $this->assertCount(1, $dispatched);
        $this->assertTrue($dispatched[0]['personId']->equals($personId));
        $this->assertSame('timetable_version_published', $dispatched[0]['type']);
        $this->assertSame(['rehearsal_id' => 'abc'], $dispatched[0]['payload']);
    }
}
