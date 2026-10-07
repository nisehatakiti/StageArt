<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

final class GetOrganizationInvitationByTokenQuery
{
    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }
}
