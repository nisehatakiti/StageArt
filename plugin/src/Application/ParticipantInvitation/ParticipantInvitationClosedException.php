<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use RuntimeException;

/**
 * R14-B §5.1 / §7.2 (409 stageart_participant_invitation_closed): the
 * invitation was invalidated by the system - the Production is closed
 * (PRODUCTION_CLOSED) or the inviter no longer has permission
 * (INVITER_REVOKED). Carries the close reason (no personal data); the
 * invitee can still reject it within its expiry.
 */
final class ParticipantInvitationClosedException extends RuntimeException
{
    private string $closeReason;

    public function __construct(string $closeReason)
    {
        parent::__construct("The participant invitation is closed ({$closeReason}).");
        $this->closeReason = $closeReason;
    }

    public function closeReason(): string
    {
        return $this->closeReason;
    }
}
