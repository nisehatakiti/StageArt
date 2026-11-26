<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use RuntimeException;

/**
 * PR #7 R12: the idempotency key was already used for a different create
 * request (different content, or another person) - 409 Conflict. Nothing
 * is created.
 */
final class RehearsalIdempotencyConflictException extends RuntimeException
{
}
