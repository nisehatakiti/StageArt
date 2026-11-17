<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

/** R14-B §7.2: no body - the invitation id and the logged-in Person only (no token). */
final class RejectParticipantInvitationCommand
{
    public int $requestedByWordPressUserId;
    public string $invitationId;

    public function __construct(int $requestedByWordPressUserId, string $invitationId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->invitationId = $invitationId;
    }
}
