<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use RuntimeException;

/**
 * Thrown when a Participant already exists for the same (Production,
 * Subject, ParticipantType) combination. Participant.md: "同一Subject・
 * 同一Productionに対するParticipantの重複を無条件に許可するものではない"
 * - a second Participant for the same Subject is allowed only with a
 * *different* ParticipantType (also enforced by a DB UNIQUE KEY).
 */
final class ParticipantAlreadyExistsException extends RuntimeException
{
}
