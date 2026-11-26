<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use RuntimeException;

/**
 * R14-B §5.3 (D6, stage 4): this invitation was already resent
 * ParticipantInvitation::MAX_RESENDS times (6 sends with the first). 429
 * `stageart_participant_invitation_resend_limit`; nothing is written.
 */
final class ParticipantInvitationResendLimitException extends RuntimeException
{
}
