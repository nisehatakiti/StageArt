<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

final class RequestNotificationEmailChangeResult
{
    public const STATUS_PENDING_VERIFICATION = 'PENDING_VERIFICATION';
    public const STATUS_ALREADY_CURRENT = 'ALREADY_CURRENT';

    public string $status;

    public function __construct(string $status)
    {
        $this->status = $status;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['status' => $this->status];
    }
}
