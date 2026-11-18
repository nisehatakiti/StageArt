<?php

declare(strict_types=1);

namespace StageArt\Infrastructure\WordPress\Retention;

use RuntimeException;

/** PR #7 R14-B stage 8: one retention step failed; carries the MySQL error number only (no error text). */
final class RetentionStepFailedException extends RuntimeException
{
    private ?int $errno;

    public function __construct(?int $errno)
    {
        parent::__construct('A retention step failed.');
        $this->errno = $errno;
    }

    public function errnoText(): string
    {
        return $this->errno === null ? 'unknown' : (string) $this->errno;
    }
}
