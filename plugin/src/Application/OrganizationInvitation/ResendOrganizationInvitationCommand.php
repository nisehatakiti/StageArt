<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

final class ResendOrganizationInvitationCommand
{
    public string $invitationId;
    public int $requestedByWordPressUserId;

    public function __construct(string $invitationId, int $requestedByWordPressUserId)
    {
        $this->invitationId = $invitationId;
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
    }
}
