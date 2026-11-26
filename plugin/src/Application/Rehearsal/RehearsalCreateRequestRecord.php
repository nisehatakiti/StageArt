<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use StageArt\Domain\Person\PersonId;
use StageArt\Domain\Rehearsal\RehearsalId;

/**
 * PR #7 R12: a recorded create request - who sent it, the hash of its
 * normalized content (RehearsalCreateRequestHash) and the Rehearsal it
 * created.
 */
final class RehearsalCreateRequestRecord
{
    public PersonId $requestedBy;
    public string $requestHash;
    public RehearsalId $rehearsalId;

    public function __construct(PersonId $requestedBy, string $requestHash, RehearsalId $rehearsalId)
    {
        $this->requestedBy = $requestedBy;
        $this->requestHash = $requestHash;
        $this->rehearsalId = $rehearsalId;
    }
}
