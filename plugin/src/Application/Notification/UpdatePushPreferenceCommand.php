<?php

declare(strict_types=1);

namespace StageArt\Application\Notification;

final class UpdatePushPreferenceCommand
{
    public int $requestedByWordPressUserId;
    public bool $enabled;

    public function __construct(int $requestedByWordPressUserId, bool $enabled)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->enabled = $enabled;
    }
}
