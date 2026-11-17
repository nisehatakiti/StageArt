<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use RuntimeException;

/**
 * R14-B §5.2 (N11, 409 stageart_participant_draft_exists): the invitee
 * already has a DRAFT Participant row of the invitation's type in this
 * Production. The acceptance changes nothing (the invitation stays
 * PENDING).
 */
final class ParticipantDraftExistsException extends RuntimeException
{
}
