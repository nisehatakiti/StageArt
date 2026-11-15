<?php

declare(strict_types=1);

namespace StageArt\Tests\Support;

use DateTimeImmutable;
use StageArt\Application\Rehearsal\RehearsalReminderSchedulerInterface;
use StageArt\Domain\Rehearsal\RehearsalId;

final class InMemoryRehearsalReminderScheduler implements RehearsalReminderSchedulerInterface
{
    /** @var array<string, DateTimeImmutable> */
    private array $scheduled = [];

    public function scheduleReminderAt(RehearsalId $rehearsalId, DateTimeImmutable $reminderAt): void
    {
        $this->scheduled[$rehearsalId->toString()] = $reminderAt;
    }

    public function cancelReminder(RehearsalId $rehearsalId): void
    {
        unset($this->scheduled[$rehearsalId->toString()]);
    }

    public function scheduledAt(RehearsalId $rehearsalId): ?DateTimeImmutable
    {
        return $this->scheduled[$rehearsalId->toString()] ?? null;
    }

    public function isScheduled(RehearsalId $rehearsalId): bool
    {
        return array_key_exists($rehearsalId->toString(), $this->scheduled);
    }
}
