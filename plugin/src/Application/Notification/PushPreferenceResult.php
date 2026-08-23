<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

final class PushPreferenceResult
{
    public bool $enabled;
    public ?string $updatedAt;

    public function __construct(bool $enabled, ?string $updatedAt)
    {
        $this->enabled = $enabled;
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'updated_at' => $this->updatedAt,
        ];
    }
}
