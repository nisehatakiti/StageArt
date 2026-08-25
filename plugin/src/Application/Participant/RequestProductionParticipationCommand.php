<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

/** Exactly one of `productionId` (search-based) or `joinKeyCode`
 * (Join Key-based) must be provided, mirroring
 * RequestOrganizationMembershipCommand's same dual entry point at the
 * Production Scope. `participantType` (CAST/STAFF) is chosen by the
 * requester up front - docs/03-InitialOnboardingAndJoinKey.md §11:
 * "出演者・スタッフ等の参加区分を選択する必要がある場合は、確認画面または
 * 次画面で選択できる". */
final class RequestProductionParticipationCommand
{
    public int $requestedByWordPressUserId;
    public ?string $productionId;
    public ?string $joinKeyCode;
    public string $participantType;

    public function __construct(int $requestedByWordPressUserId, ?string $productionId, ?string $joinKeyCode, string $participantType)
    {
        $this->requestedByWordPressUserId = $requestedByWordPressUserId;
        $this->productionId = $productionId;
        $this->joinKeyCode = $joinKeyCode;
        $this->participantType = $participantType;
    }
}
