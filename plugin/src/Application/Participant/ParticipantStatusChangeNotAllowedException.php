<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use RuntimeException;

/**
 * R14-B §5.2 / §7.1 (stage 4): PUT may change the status only ACTIVE -> INACTIVE (N6); any other change between different statuses is 422 `stageart_participant_status_change_not_allowed`.
 */
final class ParticipantStatusChangeNotAllowedException extends RuntimeException
{
}
