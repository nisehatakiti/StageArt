<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use RuntimeException;

/**
 * R14-B §5.2 / §7.1 (stage 4): PUT changes participant_type to a type the same subject already has a row of in this Production (any status), or the save hits the UNIQUE key (1062): 409 `stageart_participant_type_conflict`; the whole PUT is rolled back (N7).
 */
final class ParticipantTypeConflictException extends RuntimeException
{
}
