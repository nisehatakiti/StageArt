<?php

declare(strict_types=1);

namespace StageArt\Application\Participant;

use RuntimeException;

final class ParticipantNotFoundException extends RuntimeException
{
    public function __construct(string $participantId)
    {
        parent::__construct("Participant not found: {$participantId}");
    }
}
