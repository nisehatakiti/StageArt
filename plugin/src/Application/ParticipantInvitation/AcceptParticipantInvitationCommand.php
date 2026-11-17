<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

/** R14-B §7.2: no body - the invitation is identified only by its id and the logged-in Person (no token). */
final class AcceptParticipantInvitationCommand
{
    public int $requestedByWordPressUserId;
    public string $invitationId;

    public function __construct(int $requestedByWordPressUserId, string $invitationId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->invitationId = $invitationId;
    }
}
