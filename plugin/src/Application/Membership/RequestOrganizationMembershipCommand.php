<?php

declare(strict_types=1);

namespace StageArt\Application\Membership;

/** Exactly one of `organizationId` (search-based join) or `joinKeyCode`
 * (Join Key-based join) must be provided - see
 * docs/03-InitialOnboardingAndJoinKey.md's dual "検索" / "参加コードを持っ
 * ている" entry points, both converging on the same Membership Request. */
final class RequestOrganizationMembershipCommand
{
    public int $requestedByWordPressUserId;
    public ?string $organizationId;
    public ?string $joinKeyCode;

    public function __construct(int $requestedByWordPressUserId, ?string $organizationId, ?string $joinKeyCode)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->organizationId = $organizationId;
        $this->joinKeyCode = $joinKeyCode;
    }
}
