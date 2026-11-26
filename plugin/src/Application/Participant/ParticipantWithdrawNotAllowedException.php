<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use RuntimeException;

/** R14-B §5.2: only an ACTIVE Participant can withdraw (a WITHDRAWN one is an idempotent success). */
final class ParticipantWithdrawNotAllowedException extends RuntimeException
{
}
