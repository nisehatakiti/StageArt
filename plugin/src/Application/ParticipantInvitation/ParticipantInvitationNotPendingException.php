<?php

declare(strict_types=1);

namespace StageArt\Application\ParticipantInvitation;

use RuntimeException;

/**
 * Thrown by Resend/Cancel when the target ParticipantInvitation's status
 * is not PENDING (already CONSUMED or CANCELLED) - deliberately not
 * about expiry, which never blocks a resend (see
 * ParticipantInvitation::rotateToken()'s own docblock).
 */
final class ParticipantInvitationNotPendingException extends RuntimeException
{
}
