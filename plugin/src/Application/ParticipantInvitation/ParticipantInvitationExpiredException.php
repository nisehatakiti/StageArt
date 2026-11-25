<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use RuntimeException;

/** R14-B §5.1 (410 stageart_participant_invitation_expired): the invitation is past its expiry. */
final class ParticipantInvitationExpiredException extends RuntimeException
{
}
