<?php

declare(strict_types=1);

namespace StageArt\Application\Dashboard;

use StageArt\Application\Notification\NotificationResult;

/**
 * Deliberately UI-agnostic (§18 of this Phase's instruction): no "card"/
 * "panel"/"section" grouping, just the two flat lists DashboardPolicy.md
 * names (稽古予定 / 通知). Whichever Client consumes this (Management
 * Client HOME, a future Mobile HOME, or neither) decides layout.
 */
final class MyDashboardResult
{
    /** @var UpcomingRehearsalResult[] */
    public array $upcomingRehearsals;

    /** @var NotificationResult[] */
    public array $notifications;

    /**
     * @param UpcomingRehearsalResult[] $upcomingRehearsals
     * @param NotificationResult[] $notifications
     */
    public function __construct(array $upcomingRehearsals, array $notifications)
    {
        $this->upcomingRehearsals = $upcomingRehearsals;
        $this->notifications = $notifications;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'upcoming_rehearsals' => array_map(
                static fn (UpcomingRehearsalResult $result): array => $result->toArray(),
                $this->upcomingRehearsals
            ),
            'notifications' => array_map(
                static fn (NotificationResult $result): array => $result->toArray(),
                $this->notifications
            ),
        ];
    }
}
