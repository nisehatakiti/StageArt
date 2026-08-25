<?php

declare(strict_types=1);

namespace StageArt\Application\JoinKey;

use RuntimeException;

/** Thrown both for a code that never existed and one that is no longer
 * usable (DISABLED/EXPIRED/EXHAUSTED) - JoinKey.md never asks the Client
 * to distinguish these, so a single not-found style error is enough. */
final class JoinKeyNotFoundException extends RuntimeException
{
    public function __construct(string $code)
    {
        parent::__construct("Join Key not found or no longer usable: {$code}");
    }
}
