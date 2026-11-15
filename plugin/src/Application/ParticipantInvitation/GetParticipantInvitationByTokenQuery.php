<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

final class GetParticipantInvitationByTokenQuery
{
    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }
}
