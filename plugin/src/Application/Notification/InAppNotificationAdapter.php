<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Domain\Notification\Notification;
use StageArt\Domain\Notification\NotificationRepositoryInterface;
use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Production\ProductionId;

/**
 * The highest-priority delivery channel this phase's instruction asks
 * for (§3: "最優先で、StageArt内で通知を確認できる仕組みを完成させる") -
 * persists one `Notification` row per (Person, event), immediately
 * queryable via `ListMyNotificationsUseCase` (`GET /me/notifications`).
 * No WordPress dependency - this Adapter only needs
 * `NotificationRepositoryInterface`, so it lives in Application, not
 * Infrastructure, matching every other Repository-only class in this
 * codebase.
 */
final class InAppNotificationAdapter implements NotificationDeliveryAdapterInterface
{
    private NotificationRepositoryInterface $notifications;

    public function __construct(NotificationRepositoryInterface $notifications)
    {
        $this->notifications = $notifications;
    }

    public function deliver(PersonId $personId, string $type, array $payload): void
    {
        $message = is_string($payload['message'] ?? null) ? $payload['message'] : "StageArtからのお知らせがあります（{$type}）。";
        $productionIdString = $payload['production_id'] ?? null;
        $productionId = is_string($productionIdString) ? ProductionId::fromString($productionIdString) : null;
        $rehearsalId = is_string($payload['rehearsal_id'] ?? null) ? $payload['rehearsal_id'] : null;
        $targetType = is_string($payload['target_type'] ?? null) ? $payload['target_type'] : null;
        $targetId = is_string($payload['target_id'] ?? null) ? $payload['target_id'] : null;

        if ($targetType === null || $targetId === null) {
            $targetType = null;
            $targetId = null;
        }

        $this->notifications->save(Notification::create($personId, $type, $message, $productionId, $rehearsalId, $targetType, $targetId));
    }
}
