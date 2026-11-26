<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use RuntimeException;

/**
 * R14-B §5.3 / §11.5 (D3, stage 4): the invitation's inviter can no longer
 * manage participants, so resending it would send an invitation that can
 * never be accepted. 409 `stageart_participant_invitation_inviter_revoked`;
 * nothing is written and nobody is notified (a manager with permission
 * cancels it and invites again).
 */
final class ParticipantInvitationInviterRevokedException extends RuntimeException
{
}
