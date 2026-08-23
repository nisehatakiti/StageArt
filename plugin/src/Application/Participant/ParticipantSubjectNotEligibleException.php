<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use RuntimeException;

/**
 * Thrown when the proposed Subject (a Person or Organization, per
 * Participant.md) does not correspond to an existing record.
 */
final class ParticipantSubjectNotEligibleException extends RuntimeException
{
}
