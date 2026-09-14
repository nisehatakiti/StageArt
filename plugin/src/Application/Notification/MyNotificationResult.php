<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

use StageArt\Domain\Notification\Notification;

final class MyNotificationResult
{
    public string $id;
    public string $type;
    public string $message;
    public ?string $productionId;
    public bool $isRead;
    public string $createdAt;

    private function __construct(string $id, string $type, string $message, ?string $productionId, bool $isRead, string $createdAt)
    {
        $this->id = $id;
        $this->type = $type;
        $this->message = $message;
        $this->productionId = $productionId;
        $this->isRead = $isRead;
        $this->createdAt = $createdAt;
    }

    public static function fromDomain(Notification $notification): self
    {
        return new self(
            $notification->id()->toString(),
            $notification->type(),
            $notification->message(),
            $notification->productionId()?->toString(),
            $notification->isRead(),
            $notification->createdAt()->format(DATE_ATOM)
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
            'message' => $this->message,
            'production_id' => $this->productionId,
            'is_read' => $this->isRead,
            'created_at' => $this->createdAt,
        ];
    }
}
