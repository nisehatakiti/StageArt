<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use RuntimeException;

/**
 * R14-B §5.3 (D5, stage 4): the Production is COMPLETED / ARCHIVED /
 * CANCELLED - no invitation is created or resent. 409
 * `stageart_participant_invitation_production_closed`; nothing is written.
 */
final class ParticipantInvitationProductionClosedException extends RuntimeException
{
}
