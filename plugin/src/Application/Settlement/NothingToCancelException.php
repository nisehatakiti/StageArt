<?php

declare(strict_types=1);

namespace StageArt\Application\Settlement;

use RuntimeException;

final class NothingToCancelException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This member has no settlement recorded to cancel.');
    }
}
