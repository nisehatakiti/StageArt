<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

/**
 * R14-B §7.2 reject response. close_reason is the invitation's past
 * invalidation (PRODUCTION_CLOSED / INVITER_REVOKED) when there was one.
 */
final class RejectParticipantInvitationResult
{
    public string $invitationId;
    public string $status;
    public ?string $closeReason;

    public function __construct(string $invitationId, string $status, ?string $closeReason)
    {
        $this->invitationId = $invitationId;
        $this->status = $status;
        $this->closeReason = $closeReason;
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->invitationId,
            'status' => $this->status,
            'close_reason' => $this->closeReason,
        ];
    }
}
