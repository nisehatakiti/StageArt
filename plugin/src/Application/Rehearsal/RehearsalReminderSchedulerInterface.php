<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use DateTimeImmutable;
use StageArt\Domain\Rehearsal\RehearsalId;

/**
 * Phase 7 (Rehearsal仕様整合): the Port a future time-triggered Reminder
 * dispatch is scheduled through - kept as an Application-layer Contract
 * (not a Core Contract, since only the Rehearsal Module needs it today)
 * so Create/UpdateRehearsalUseCase never depend on a concrete WordPress
 * Cron class directly, matching this codebase's existing Module
 * boundary convention (`RehearsalModuleBootstrap` never touches
 * `Infrastructure\WordPress\*` concretely).
 */
interface RehearsalReminderSchedulerInterface
{
    public function scheduleReminderAt(RehearsalId $rehearsalId, DateTimeImmutable $reminderAt): void;

    public function cancelReminder(RehearsalId $rehearsalId): void;
}
