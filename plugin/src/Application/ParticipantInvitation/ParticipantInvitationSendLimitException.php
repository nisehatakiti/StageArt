<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use DateTimeImmutable;
use RuntimeException;

/**
 * R14-B §5.3 (stage 4): the same Production and address already had
 * InvitationSendService::MAX_SENDS_PER_ADDRESS sends within 30 days (all
 * types and invitations together, cancelled / expired / rejected ones
 * included). 429 `stageart_participant_invitation_send_limit` with
 * `available_at` - when enough of those sends leave the 30-day window.
 * Nothing is written.
 */
final class ParticipantInvitationSendLimitException extends RuntimeException
{
    private DateTimeImmutable $availableAt;

    public function __construct(DateTimeImmutable $availableAt)
    {
        parent::__construct('This address has reached the limit of invitation e-mails for this production within 30 days.');
        $this->availableAt = $availableAt;
    }

    public function availableAt(): DateTimeImmutable
    {
        return $this->availableAt;
    }
}
