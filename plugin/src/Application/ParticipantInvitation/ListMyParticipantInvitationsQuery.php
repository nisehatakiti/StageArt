<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

final class ListMyParticipantInvitationsQuery
{
    public int $requestedByWordPressUserId;

    public function __construct(int $requestedByWordPressUserId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
