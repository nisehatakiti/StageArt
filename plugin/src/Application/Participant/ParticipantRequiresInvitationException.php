<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use RuntimeException;

/**
 * R14-B §7.1 (D10, stage 4): POST /productions/{id}/participants with
 * subject_type PERSON or ORGANIZATION - a Person takes part only with their
 * own consent (invitation acceptance or an approved join request), and an
 * organization cannot be added at all. 422
 * `stageart_participant_requires_invitation`; nothing is written.
 */
final class ParticipantRequiresInvitationException extends RuntimeException
{
}
