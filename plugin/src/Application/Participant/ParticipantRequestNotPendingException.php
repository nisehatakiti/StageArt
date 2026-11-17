<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use RuntimeException;

/** R14-B §4.7 (409 stageart_participant_request_not_pending): the join request was already handled. */
final class ParticipantRequestNotPendingException extends RuntimeException
{
}
