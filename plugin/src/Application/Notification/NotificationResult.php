<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Domain\Notification\TimetableVersionPublishedNotification;

/**
 * Wraps the sole Notification Fact type that exists today
 * (TimetableVersionPublishedNotification). A `type` discriminator field
 * is included from the start so the response shape does not need to
 * change when other Notification Categories (see Notification.md's
 * "# Future") are added as additional Fact types later.
 */
final class NotificationResult
{
    public string $id;
    public string $type;
    public string $productionId;
    public string $rehearsalId;
    public string $timetableId;
    public int $version;
    public string $publishedBy;
    public string $publishedAt;
    public ?string $changeSummary;
    public string $createdAt;
    public bool $isRead;

    private function __construct(
        string $id,
        string $type,
        string $productionId,
        string $rehearsalId,
        string $timetableId,
        int $version,
        string $publishedBy,
        string $publishedAt,
        ?string $changeSummary,
        string $createdAt,
        bool $isRead
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->productionId = $productionId;
        $this->rehearsalId = $rehearsalId;
        $this->timetableId = $timetableId;
        $this->version = $version;
        $this->publishedBy = $publishedBy;
        $this->publishedAt = $publishedAt;
        $this->changeSummary = $changeSummary;
        $this->createdAt = $createdAt;
        $this->isRead = $isRead;
    }

    /**
     * `$isRead` is resolved by the caller (ListNotificationsForProductionUseCase)
     * from a single bulk NotificationReadState lookup across every
     * Notification being returned - never looked up per-instance here,
     * to avoid an N+1 query per row.
     */
    public static function fromTimetableVersionPublished(TimetableVersionPublishedNotification $notification, bool $isRead): self
    {
        return new self(
            $notification->id()->toString(),
            'timetable_version_published',
            $notification->productionId()->toString(),
            $notification->rehearsalId()->toString(),
            $notification->timetableId()->toString(),
            $notification->version(),
            $notification->publishedBy()->toString(),
            $notification->publishedAt()->format(DATE_ATOM),
            $notification->changeSummary(),
            $notification->createdAt()->format(DATE_ATOM),
            $isRead
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'production_id' => $this->productionId,
            'rehearsal_id' => $this->rehearsalId,
            'timetable_id' => $this->timetableId,
            'version' => $this->version,
            'published_by' => $this->publishedBy,
            'published_at' => $this->publishedAt,
            'change_summary' => $this->changeSummary,
            'created_at' => $this->createdAt,
            'is_read' => $this->isRead,
        ];
    }
}
