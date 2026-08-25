<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

final class ApproveParticipantRequestCommand
{
    public int $requestedByWordPressUserId;
    public string $participantId;

    public function __construct(int $requestedByWordPressUserId, string $participantId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->participantId = $participantId;
    }
}
