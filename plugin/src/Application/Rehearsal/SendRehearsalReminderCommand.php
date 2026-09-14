<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

final class SendRehearsalReminderCommand
{
    public string $rehearsalId;

    public function __construct(string $rehearsalId)
    {
        $this->rehearsalId = $rehearsalId;
    }
}
