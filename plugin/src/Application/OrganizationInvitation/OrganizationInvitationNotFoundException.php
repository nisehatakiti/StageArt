<?php

declare(strict_types=1);

namespace StageArt\Application\OrganizationInvitation;

use RuntimeException;

final class OrganizationInvitationNotFoundException extends RuntimeException
{
    public function __construct(string $id)
    {
        parent::__construct("OrganizationInvitation not found: {$id}");
    }
}
