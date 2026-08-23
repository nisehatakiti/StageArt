<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use RuntimeException;

final class RehearsalNotFoundException extends RuntimeException
{
    public function __construct(string $rehearsalId)
    {
        parent::__construct("Rehearsal not found: {$rehearsalId}");
    }
}
