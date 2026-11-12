<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

final class NotificationEmailSettingsResult
{
    public ?string $currentEmail;
    public string $source;
    public ?string $pendingEmail;

    public function __construct(?string $currentEmail, string $source, ?string $pendingEmail)
    {
        $this->currentEmail = $currentEmail;
        $this->source = $source;
        $this->pendingEmail = $pendingEmail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'current_email' => $this->currentEmail,
            'source' => $this->source,
            'pending_email' => $this->pendingEmail,
        ];
    }
}
