<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

/**
 * PR #7 R12: the created Rehearsal, and whether it was created by an
 * earlier request with the same idempotency key (`replayed` - nothing was
 * created and no notification was sent this time; REST answers 200
 * instead of 201).
 */
final class CreateRehearsalOutcome
{
    public RehearsalResult $result;
    public bool $replayed;

    public function __construct(RehearsalResult $result, bool $replayed)
    {
        $this->result = $result;
        $this->replayed = $replayed;
    }
}
