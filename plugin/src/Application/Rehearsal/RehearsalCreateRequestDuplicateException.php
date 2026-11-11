<?php

declare(strict_types=1);

namespace StageArt\Application\Rehearsal;

use RuntimeException;

/**
 * PR #7 R12: the idempotency key was recorded by another request first
 * (the DB UNIQUE constraint). CreateRehearsalUseCase rolls back its own
 * work and answers with the other request's Rehearsal, or 409.
 */
final class RehearsalCreateRequestDuplicateException extends RuntimeException
{
}
