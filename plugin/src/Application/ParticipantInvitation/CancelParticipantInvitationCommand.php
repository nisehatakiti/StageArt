<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

final class CancelParticipantInvitationCommand
{
    public string $invitationId;
    public int $requestedByWordPressUserId;

    public function __construct(string $invitationId, int $requestedByWordPressUserId)
    {
        $this->invitationId = $invitationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
