<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

final class ConfirmRehearsalCommand
{
    public string $rehearsalId;
    public int $requestedByWordPressUserId;

    public function __construct(string $rehearsalId, int $requestedByWordPressUserId)
    {
        $this->rehearsalId = $rehearsalId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
