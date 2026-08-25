<?php

declare(strict_types=1);

namespace StageArt\Application\Membership;

use RuntimeException;

final class MembershipRequestNotFoundException extends RuntimeException
{
    public function __construct(string $membershipId)
    {
        parent::__construct("Membership request not found: {$membershipId}");
    }
}
