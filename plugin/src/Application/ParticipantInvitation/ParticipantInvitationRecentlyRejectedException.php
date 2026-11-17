<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateTimeImmutable;
use RuntimeException;

/**
 * R14-B §5.3 (D6, stage 4): the invitee rejected an invitation of this
 * Production sent to the same address (any type) less than 30 days ago,
 * so no new invitation is created. 409
 * `stageart_participant_invitation_recently_rejected` with `available_at`
 * (30 days after the latest rejection). Nothing is written.
 */
final class ParticipantInvitationRecentlyRejectedException extends RuntimeException
{
    private DateTimeImmutable $availableAt;

    public function __construct(DateTimeImmutable $availableAt)
    {
        parent::__construct('This address declined an invitation to this production recently.');
        $this->availableAt = $availableAt;
    }

    public function availableAt(): DateTimeImmutable
    {
        return $this->availableAt;
    }
}
