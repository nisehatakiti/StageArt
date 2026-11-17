<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use RuntimeException;

/**
 * R14-B §5.2 / §7.1 (stage 4): DELETE of a PENDING / DRAFT / REJECTED / WITHDRAWN Participant (N10): 409 `stageart_participant_cancel_not_allowed`; nothing changes (a pending request is ended by rejecting it).
 */
final class ParticipantCancelNotAllowedException extends RuntimeException
{
}
