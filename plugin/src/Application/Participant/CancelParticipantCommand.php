<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

final class CancelParticipantCommand
{
    public string $participantId;
    public int $requestedByWordPressUserId;

    public function __construct(string $participantId, int $requestedByWordPressUserId)
    {
        $this->participantId = $participantId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
