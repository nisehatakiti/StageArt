<?php

declare(strict_types=1);

namespace StageArt\Application\Membership;

final class ApproveMembershipRequestCommand
{
    public int $requestedByWordPressUserId;
    public string $membershipId;

    public function __construct(int $requestedByWordPressUserId, string $membershipId)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->membershipId = $membershipId;
    }
}
