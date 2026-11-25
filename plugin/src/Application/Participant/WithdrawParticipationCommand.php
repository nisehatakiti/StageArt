<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

final class WithdrawParticipationCommand
{
    public int $requestedByWordPressUserId;
    public string $participantId;

    public function __construct(int $requestedByWordPressUserId, string $participantId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->participantId = $participantId;
    }
}
